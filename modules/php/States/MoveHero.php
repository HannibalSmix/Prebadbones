<?php

declare(strict_types=1);
namespace Bga\Games\PreBadBones\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\PreBadBones\Game;

class MoveHero extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 2,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: clienttranslate('Other players must move their hero'),
            descriptionMyTurn: clienttranslate('${you} must move your hero'),
            transitions: ['allHerosMoved' => 3],
        );
    }

    public function onEnteringState(): void {
        $this->game->gamestate->setAllPlayersMultiactive();
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array 
    {
       /* return [
            'validCells' => $this->getValidMoves($activePlayerId),
        ];*/
        /*
        $moves = $this->getValidMoves($activePlayerId);
        $this->game->dump('moves', $moves);
        $this->game->dump('activePlayerId', $activePlayerId);
        // Return valid square
        return [
            '_private' => [
                $activePlayerId => [
                    'validCells' => $this->getValidMoves($activePlayerId),
                ]
            ]
        ];*/
                // Retourne les cases valides pour chaque joueur actif
        $result = [];
        $activePlayers = $this->game->gamestate->getActivePlayerList();
        foreach ($activePlayers as $player_id) {
            $result['_private'][$player_id] = [
                'validCells' => $this->getValidMoves((int)$player_id),
            ];
        }
        return $result;

    }   

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws UserException
     */
    #[PossibleAction]
    public function actMoveHero(string $cell, int $currentPlayerId ): void {

        // Extraire x et y depuis "cell_{player_id}_{x}_{y}"
        $parts = explode('_', $cell);
        $x = (int)$parts[2];
        $y = (int)$parts[3];

        $this->game->dump('cell recu', $cell);
        $this->game->dump('parts', $parts);
        $this->game->dump('x', $x);
        $this->game->dump('y', $y);
        
        // Récupérer la position actuelle du héros
        $hero = $this->game->getObjectFromDb(
            "SELECT `token_location` FROM `hero` WHERE `token_key` = 'hero_{$currentPlayerId}'"
        );
        
        // Extraire x et y actuels depuis "cell_{player_id}_{x}_{y}"
        $parts = explode('_', $hero['token_location']);
        $currentX = (int)$parts[2];
        $currentY = (int)$parts[3];

        $this->game->dump('hero location', $hero['token_location']);
        $this->game->dump('currentX', $currentX);
        $this->game->dump('currentY', $currentY);

        // Vérifier que le mouvement est valide (adjacent orthogonal ou diagonal)
        $dx = abs($x - $currentX);
        $dy = abs($y - $currentY);
        if ($dx > 1 || $dy > 1 || ($dx === 0 && $dy === 0)) {
            throw new UserException(clienttranslate('Invalid move'));
        }

        // Vérifier que la case est dans le board (1-5)
        if ($x < 1 || $x > 5 || $y < 1 || $y > 5) {
            throw new UserException(clienttranslate('Cannot leave the board'));
        }

        $newLocation = "cell_{$currentPlayerId}_{$x}_{$y}";

        // Détruire les squelettes sur la case d'arrivée
        $this->game->DbQuery(
            "UPDATE `skeleton` SET `token_location` = 'bag' 
             WHERE `token_location` = '{$newLocation}'"
        );

        // Déplacer le héros
        $this->game->DbQuery(
            "UPDATE `hero` SET `token_location` = '{$newLocation}' 
             WHERE `token_key` = 'hero_{$currentPlayerId}'"
        );

        // Notifier les joueurs
        $this->game->bga->notify->all('heroMoved', clienttranslate('${player_name} moves their hero'), [
            'player_id'   => $currentPlayerId,
            'player_name' => $this->game->getPlayerNameById($currentPlayerId),
            'cell'           => $newLocation
        ]);

        // Signaler que ce joueur a terminé sa phase
        $this->game->gamestate->setPlayerNonMultiactive($currentPlayerId, 'allHerosMoved');
       //return 'allHerosMoved';
    }

    private function getValidMoves(int $player_id): array {
        $hero = $this->game->getObjectFromDb(
            "SELECT `token_location` FROM `hero` WHERE `token_key` = 'hero_{$player_id}'"
        );
        $parts = explode('_', $hero['token_location']);
        $currentX = (int)$parts[2];
        $currentY = (int)$parts[3];

        $validCells = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($dx === 0 && $dy === 0) continue;
                $nx = $currentX + $dx;
                $ny = $currentY + $dy;
                if ($nx >= 1 && $nx <= 5 && $ny >= 1 && $ny <= 5) {
                    $validCells[] = "cell_{$player_id}_{$nx}_{$ny}";
                }
            }
        }
        return $validCells;
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: play a random card).
     * 
     * See more about Zombie Mode: https://en.doc.boardgamearena.com/Zombie_Mode
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, 
     * but use the $playerId passed in parameter and $this->game->getPlayerNameById($playerId) instead.
     */
    public function zombie(int $playerId): string {
        $moves = $this->getValidMoves($playerId);
        if (!empty($moves)) {
            $cell = $moves[array_rand($moves)];
            $this->actMoveHero($cell, $playerId);
        }
        return 'allHerosMoved';
    }
}