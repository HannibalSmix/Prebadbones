<?php
declare(strict_types=1);
namespace Bga\Games\PreBadBones\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\PreBadBones\Game;

class MoveSkeletons extends GameState
{
    function __construct(protected Game $game) {
        parent::__construct($game,
            id: 4,
            type: StateType::GAME,
            transitions: ['allSkeletonsMoved' => 5],
        );
    }

    public function onEnteringState(): void {
        $players = $this->game->loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            $skeletons = $this->game->getCollectionFromDb(
                "SELECT * FROM `skeleton` 
                 WHERE `token_location` LIKE 'cell_{$player_id}_%'
                 OR `token_location` LIKE 'entrance_%_{$player_id}'"
            );

            foreach ($skeletons as $skeleton) {
                $this->moveSkeleton($skeleton, (int)$player_id);
            }
        }

        //return 'allSkeletonsMoved';
    }

    private function moveSkeleton(array $skeleton, int $player_id): void {
        $key = $skeleton['token_key'];
        $location = $skeleton['token_location'];

        // Déterminer la direction depuis la clé : skeleton_blue_left_3 → left
        $parts = explode('_', $key);
        $direction = $parts[2]; // top, left, right

        // Calculer la nouvelle position
        [$newX, $newY] = $this->getNewPosition($location, $direction, $player_id);

        // Cas 1 : squelette sort du board → attaque le village
        if ($newX === null) {
            $this->attackVillage($key, $player_id);
            return;
        }

        // Cas 2 : squelette arrive sur la tour (3,3)
        if ($newX === 3 && $newY === 3) {
            $this->destroyTowerFloor($key, $player_id);
            return;
        }

        // Cas 3 : déplacement normal
        $newLocation = "cell_{$player_id}_{$newX}_{$newY}";
        $this->game->DbQuery(
            "UPDATE `skeleton` SET `token_location` = '{$newLocation}' 
             WHERE `token_key` = '{$key}'"
        );

        $this->game->bga->notify->all('skeletonMoved', '', [
            'player_id'  => $player_id,
            'token_key'  => $key,
            'location'   => $newLocation,
        ]);
    }

    private function getNewPosition(string $location, string $direction, int $player_id): array {
        // Si le squelette est encore dans sa zone d'entrée → entre sur le board
        if (str_starts_with($location, 'entrance_')) {
            return match($direction) {
                'top'   => [3, 1], // entre par le haut → ligne 1, colonne 3
                'left'  => [1, 3], // entre par la gauche → colonne 1, ligne 3
                'right' => [5, 3], // entre par la droite → colonne 5, ligne 3
                default => [null, null],
            };
        }

        // Squelette déjà sur le board : extraire x,y
        $parts = explode('_', $location); // cell_playerid_x_y
        $x = (int)$parts[2];
        $y = (int)$parts[3];

        return match($direction) {
            'top'   => $y + 1 > 5 ? [null, null] : [$x, $y + 1], // descend (y++)
            'left'  => $x + 1 > 5 ? [null, null] : [$x + 1, $y], // va à droite (x++)
            'right' => $x - 1 < 1 ? [null, null] : [$x - 1, $y], // va à gauche (x--)
            default => [null, null],
        };
    }

    private function attackVillage(string $skeletonKey, int $player_id): void {
        // Trouver la première maison encore intacte (token_state = 1)
        $village = $this->game->getObjectFromDb(
            "SELECT * FROM `village` WHERE `token_key` = '{$player_id}'"
        );

        if (!$village || (int)$village['token_state'] <= 0) {
            // Plus de maisons → squelette retourne au bag quand même
            $this->sendToBag($skeletonKey, $player_id);
            return;
        }

        // Décrémenter le nombre de maisons
        $newState = (int)$village['token_state'] - 1;
        $this->game->DbQuery(
            "UPDATE `village` SET `token_state` = {$newState} 
             WHERE `token_key` = '{$player_id}'"
        );

        $this->game->playerStats->inc($player_id, 'houses_lost', 1);

        $this->game->bga->notify->all('houseLost', 
            clienttranslate('${player_name} loses a house!'), [
            'player_id'   => $player_id,
            'player_name' => $this->game->getPlayerNameById($player_id),
            'newState'    => $newState,
        ]);

        $this->sendToBag($skeletonKey, $player_id);
    }

    private function destroyTowerFloor(string $skeletonKey, int $player_id): void {
        $tower = $this->game->getObjectFromDb(
            "SELECT * FROM `tower` WHERE `token_key` = '{$player_id}'"
        );

        if (!$tower || (int)$tower['token_state'] <= 0) {
            $this->sendToBag($skeletonKey, $player_id);
            return;
        }

        $newState = (int)$tower['token_state'] - 1;
        $this->game->DbQuery(
            "UPDATE `tower` SET `token_state` = {$newState} 
             WHERE `token_key` = '{$player_id}'"
        );

        $this->game->playerStats->inc($player_id, 'tower_floors_lost', 1);

        $this->game->bga->notify->all('towerFloorLost', 
            clienttranslate('${player_name} loses a tower floor!'), [
            'player_id'   => $player_id,
            'player_name' => $this->game->getPlayerNameById($player_id),
            'newState'    => $newState,
        ]);

        $this->sendToBag($skeletonKey, $player_id);
    }

    private function sendToBag(string $skeletonKey, int $player_id): void {
        $this->game->DbQuery(
            "UPDATE `skeleton` SET `token_location` = 'bag' 
             WHERE `token_key` = '{$skeletonKey}'"
        );

        $this->game->bga->notify->all('skeletonToBag', '', [
            'player_id' => $player_id,
            'token_key' => $skeletonKey,
        ]);
    }
}