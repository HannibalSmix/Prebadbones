<?php
declare(strict_types=1);
namespace Bga\Games\PreBadBones\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\PreBadBones\Game;

class PlaceTrap extends GameState
{
    function __construct(protected Game $game) {
        parent::__construct($game,
            id: 3,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: clienttranslate('Other players must place/retrieve a trap or do nothing'),
            descriptionMyTurn: clienttranslate('${you} must place/retrieve a trap, or do nothing'),
            transitions: ['allTrapsDone' => 4],
        );
    }

    public function onEnteringState(): void {
        $this->game->gamestate->setAllPlayersMultiactive();
    }

    //return trap from players (on the board and supply)
    public function getArgs(int $activePlayerId): array {
        $players = $this->game->gamestate->getActivePlayerList();
        $result = [];
        foreach ($players as $player_id) {
            $result['_private'][$player_id] = [
                'supplyTraps' => $this->getSupplyTraps((int)$player_id),
                'boardTraps'  => $this->getBoardTraps((int)$player_id),
            ];
        }
        return $result;
    }

    #[PossibleAction]
    public function actPlaceTrap(string $trapKey, string $cell, int $orientation, int $activePlayerId): void {

        // Extraire x et y depuis "cell_{player_id}_{x}_{y}"
        $parts = explode('_', $cell);
        $x = (int)$parts[2];
        $y = (int)$parts[3];

        // Vérifier que le piège appartient au joueur et est dans son supply
        $trap = $this->game->getObjectFromDb(
            "SELECT * FROM `trap` WHERE `token_key` = '{$trapKey}' 
             AND `token_location` = 'supply_{$activePlayerId}'
             AND `token_key LIKE '%_{$activePlayerId}_%'"
        );
        if (!$trap) {
            throw new UserException(clienttranslate('Trap not available'));
        }

        // Vérifier que la case est vide (pas de piège, pas de tour, pas de squelette)
        $targetLocation = "cell_{$activePlayerId}_{$x}_{$y}";
        $towerLocation  = "cell_{$activePlayerId}_3_3"; // la tour est au centre

        if ($targetLocation === $towerLocation) {
            throw new UserException(clienttranslate('Cannot place trap on tower'));
        }

        $occupied = $this->game->getObjectFromDb(
            "SELECT * FROM `trap` WHERE `token_location` = '{$targetLocation}'"
        );
        if ($occupied) {
            throw new UserException(clienttranslate('Cell already has a trap'));
        }

        // Placer le piège
        $this->game->DbQuery(
            "UPDATE `trap` SET `token_location` = '{$targetLocation}', 
             `token_orientation` = {$orientation}, `token_state` = 0
             WHERE `token_key` = '{$trapKey}'"
        );

        $this->game->bga->notify->all('trapPlaced', clienttranslate('${player_name} places a trap'), [
            'player_id'   => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'cell'        => $targetLocation,
            'orientation' => $orientation,
        ]);

        $this->game->gamestate->setPlayerNonMultiactive($activePlayerId, 'allTrapsDone');
        //return 'allTrapsDone';
    }

    #[PossibleAction]
    public function actRetrieveTrap(string $trapKey, string $cell, int $activePlayerId): void {
        // Vérifier que le piège est sur le board du joueur
        $trap = $this->game->getObjectFromDb(
            "SELECT * FROM `trap` WHERE `token_location` = '{$cell}'
            AND `token_location` LIKE 'cell_{$activePlayerId}_%'"
        );
        if (!$trap) {
            throw new UserException(clienttranslate('Trap not on your board'));
        }

        $this->game->DbQuery(
            "UPDATE `trap` SET `token_location` = 'supply_{$activePlayerId}', `token_state` = 0 
            WHERE `token_key` = '{$trap['token_key']}'"
        );

        $this->game->bga->notify->all('trapRetrieved', clienttranslate('${player_name} retrieves a trap'), [
            'player_id'   => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'cell'        => $cell,
        ]);

        $this->game->gamestate->setPlayerNonMultiactive($activePlayerId, 'allTrapsDone');
        //return 'allTrapsDone';
    }

    #[PossibleAction]
    public function actDoNothing(int $activePlayerId): void {
        $this->game->bga->notify->all('trapSkipped', clienttranslate('${player_name} does nothing'), [
            'player_id'   => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
        ]);

        $this->game->gamestate->setPlayerNonMultiactive($activePlayerId, 'allTrapsDone');
        //return 'allTrapsDone';
    }

    private function getSupplyTraps(int $player_id): array {
        return $this->game->getCollectionFromDb(
            "SELECT * FROM `trap` WHERE `token_location` = 'supply_{$player_id}'"
        );
    }

    private function getBoardTraps(int $player_id): array {
        return $this->game->getCollectionFromDb(
            "SELECT * FROM `trap` WHERE `token_location` LIKE 'cell_{$player_id}_%'"
        );
    }

    public function zombie(int $playerId): void {
        $this->actDoNothing($playerId);
    }
}