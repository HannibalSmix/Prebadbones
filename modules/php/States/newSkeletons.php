<?php

declare(strict_types=1);
namespace Bga\Games\PreBadBones\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\UserException;
use Bga\Games\PreBadBones\Game;

class NewSkeletons extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 5,
            type: StateType::GAME,
            transitions: ['newSkeletons' => 2],
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function onEnteringState(): void {

        // // Give some extra time to the active player when he completed an action
        // //$this->game->giveExtraTime($activePlayerId);
        
        // //$this->game->activeNextPlayer();

        // // Go to another gamestate
        // /*$gameEnd = false; // Here, we would detect if the game is over to make the appropriate transition
        // if ($gameEnd) {
        //     return EndScore::class;
        // } else {
        //     return MoveHero::class;
        // }*/
        $this->drawThreeSkeleton();
        $this->MoveSkeletonsFromCimetery();
        $this->game->gamestate->nextState('newSkeletons');

    }

    function MoveSkeletonsFromCimetery()
    {
        $sql = "SELECT token_key, token_location FROM skeleton WHERE token_location LIKE 'cimetery_%'";
        $skeletons = $this->game->getCollectionFromDb($sql,true); 

        //$this->dump('skeletonsskeletonsskeletonsskeletonsskeletons', $skeletons);
        foreach($skeletons as $key => $location){
            //$this->dump('token_keytoken_keytoken_keytoken_key', $token_key);
            $parts = explode('_', $key);
            $base = $parts[0] . '_' . $parts[1] . '_' . $parts[2]; // skeleton_blue_left
            $parts = explode('_', $location);
            $player_id = $parts[1];
            $new_loc='entrance_'.$base.'_'.$player_id;

            $this->game->DbQuery(
                "UPDATE `skeleton` SET `token_location`='$new_loc' WHERE `token_key` = '$key'"
            );

            $this->game->bga->notify->all('skeletonPlacement', '', [
                'player_id' => $player_id,
                'token_key' => $key,
                'location'  => $new_loc,
            ]);
        }

    }

    function drawThreeSkeleton()//: array
    {
        $players = $this->game->loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            $sql = "SELECT token_key FROM skeleton WHERE token_location = 'bag' ORDER BY RAND() LIMIT 3";
            $skeletons = $this->game->getObjectListFromDB($sql,true); 

            foreach($skeletons as $value){
                //$this->dump('token_keytoken_keytoken_keytoken_key', $token_key);
                //$parts = explode('_', $value);
                //$base = $parts[0] . '_' . $parts[1] . '_' . $parts[2]; // skeleton_blue_left
                $new_loc='cimetery_'.$player_id;

                $this->game->DbQuery(
                    "UPDATE `skeleton` SET `token_location`='$new_loc' WHERE `token_key` = '$value'"
                );

                $this->game->bga->notify->all('skeletonDrawn', '', [
                    'player_id' => $player_id,
                    'token_key' => $value,
                    'location'  => $new_loc,
                ]);
            }
        }
    }
}