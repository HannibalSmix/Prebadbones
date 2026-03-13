<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * PreBadBones implementation : © <Vanwesel Etienne> <e.vanwesel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\PreBadBones;

use Bga\Games\PreBadBones\States\MoveHero;
use Bga\GameFramework\Components\Counters\PlayerCounter;

class Game extends \Bga\GameFramework\Table
{
    public static array $CARD_TYPES;

    public PlayerCounter $playerEnergy;

    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If you want to store any type instead of int, use $this->globals instead.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();
        require 'material.inc.php'; 

        $this->initGameStateLabels([]); // mandatory, even if the array is empty

        $this->playerEnergy = $this->bga->counterFactory->createPlayerCounter('energy');

        /* example of notification decorator.
        // automatically complete notification args when needed
        $this->bga->notify->addDecorator(function(string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
        
            if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
                $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
                $args['i18n'][] = ['card_name'];
            }
            
            return $args;
        });*/
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use `DBPREFIX_<table_name>` for all tables
//
//            $sql = "ALTER TABLE `DBPREFIX_xxxxxxx` ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use `DBPREFIX_<table_name>` for all tables
//
//            $sql = "CREATE TABLE `DBPREFIX_xxxxxxx` ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas(int $currentPlayerId): array
    {
        $result = [];
        // WARNING: We must only return information visible by the current player (using $currentPlayerId).

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`"
        );
        $this->playerEnergy->fillResult($result);

        // TODO: Gather all information about current game situation (visible by player $currentPlayerId).
        // Squelettes (seulement ceux sur le board, pas dans le bag)
        $result["skeletons"] = $this->getCollectionFromDb(
            "SELECT * FROM `skeleton` WHERE `token_location` != 'bag'"
        );

        // Héros
        $result["heroes"] = $this->getCollectionFromDb(
            "SELECT * FROM `hero`"
        );

        // Pièges
        $result["traps"] = $this->getCollectionFromDb(
            "SELECT * FROM `trap`"
        );

        // Tours
        $result["towers"] = $this->getCollectionFromDb(
            "SELECT * FROM `tower`"
        );

        // Villages
        $result["villages"] = $this->getCollectionFromDb(
            "SELECT * FROM `village`"
        );

        return $result;
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        $this->playerEnergy->initDb(array_keys($players), initialValue: 2);

        //Création des skelettes dans le bag
        $insert=[];
        foreach($this->token_skeleton as $key => $value){
            for($i=1;$i<=12;$i++){
                $k=$key."_".$i;
                $insert[] = vsprintf("('%s', '%s', '%s', '%s')", [
                    $k,
                    'bag',
                    0,
                    0
                ]);
            }
        }
        
        static::DbQuery(
            sprintf(
                "INSERT INTO `skeleton` (`token_key`, `token_location`, `token_state`, `token_direction`) VALUES %s",
                implode(",", $insert)
            )
        );

        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        $query_values_hero=[];
        $query_values=[];
        $traps=[];
        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                addslashes($player["player_name"]),
            ]);

            //create heros
            $query_values_hero[] = vsprintf("('%s', '%s')", [
                'hero_'.$player_id,
                'cell_'.$player_id.'_3_3',
            ]);

            //draw 3 skeletons -> bag to cimetery ->to change and put on the board
            //$this->dump('BEFORE DRAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAW', $player_id);
            $this->drawThreeSkeletonNoRed($player_id);

            //create towers
            static::DbQuery(
                "INSERT INTO `tower` (`token_key`, `token_state`) VALUES ($player_id, 4)"
            );
            //create village
            static::DbQuery(
                "INSERT INTO `village` (`token_key`, `token_state`) VALUES ($player_id, 5)"
            );

            //traps
            $traps[] = "('catapult_{$player_id}_1','supply',0,0)";
            $traps[] = "('catapult_{$player_id}_2','supply',0,0)";
            $traps[] = "('wall_{$player_id}_1','supply',0,0)";
            $traps[] = "('wall_{$player_id}_2','supply',0,0)";
            $traps[] = "('dragon_{$player_id}','supply',0,0)";
            $traps[] = "('treasure_{$player_id}','supply',0,0)";

          
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(",", $query_values)
            )
        );

        //create heros
        static::DbQuery(
            sprintf(
                "INSERT INTO `hero` (`token_key`, `token_location`) VALUES %s",
                implode(",", $query_values_hero)
            )
        );
        
        //create traps
        $this->DbQuery("INSERT INTO `trap` (`token_key`, `token_location`, `token_state`, `token_orientation`) VALUES " . implode(',', $traps));
        

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // Init global values with their initial values.

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.json` file.
        // Init game statistics
        $this->tableStats->init(['turns_number'], 0);

        $this->playerStats->init([
            'turns_number',
            'skeletons_killed_by_hero',
            'skeletons_sent_to_opponents',
            'tower_floors_lost',
            'houses_lost',
            'traps_destroyed',
            'final_score'
        ], 0);
        


        // Dummy content.
        // $this->tableStats->init('table_teststat1', 0);
        // $this->playerStats->init('player_teststat1', 0);

        // TODO: Setup the initial game situation here.

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();

        return MoveHero::class;
    }

    //Draw 3 skeletons in the bag
    function drawThreeSkeleton($player_id)//: array
    {
        $sql = "SELECT token_key FROM skeleton WHERE token_location = 'bag' ORDER BY RAND() LIMIT 3";
        $skeletons = self::getCollectionFromDb($sql);
        $new_loc='cimetery_'.$player_id;

        $token_keys = array_map(fn($s) => "'" . $s['token_key'] . "'", $skeletons);
        $keys_str = implode(",", $token_keys);
            
        static::DbQuery(
            "UPDATE `skeleton` SET `token_location`='$new_loc' WHERE `token_key` IN ($keys_str)"
        );
    
        //return $result;
    }
    //Draw 3 skeletons except RED skeleton in the bag!!
    function drawThreeSkeletonNoRed($player_id)//: array
    {
        //$this->dump('DRAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAW', $player_id);
        $sql = "SELECT token_key FROM skeleton WHERE token_location = 'bag' AND token_key NOT LIKE '%red%'  ORDER BY RAND() LIMIT 3";
        $skeletons = self::getObjectListFromDB($sql,true); 

        //$this->dump('skeletonsskeletonsskeletonsskeletonsskeletons', $skeletons);
        foreach($skeletons as $value){
            //$this->dump('token_keytoken_keytoken_keytoken_key', $token_key);
            $parts = explode('_', $value);
            $base = $parts[0] . '_' . $parts[1] . '_' . $parts[2]; // skeleton_blue_left
            $new_loc='entrance_'.$base.'_'.$player_id;

            static::DbQuery(
                "UPDATE `skeleton` SET `token_location`='$new_loc' WHERE `token_key` = '$value'"
            );
        }

       // $token_keys = array_map(fn($s) => "'" . $s['token_key'] . "'", $skeletons);
        //$keys_str = implode(",", $token_keys);
          
    
        //return $result;
    }

    /**
     * Example of debug function.
     * Here, jump to a state you want to test (by default, jump to next player state)
     * You can trigger it on Studio using the Debug button on the right of the top bar.
     */
    public function debug_goToState(int $state = 3) {
        $this->gamestate->jumpToState($state);
    }

    /**
     * Another example of debug function, to easily test the zombie code.
     */
    public function debug_playOneMove() {
        $this->bga->debug->playUntil(fn(int $count) => $count == 1);
    }

    /*
    Another example of debug function, to easily create situations you want to test.
    Here, put a card you want to test in your hand (assuming you use the Deck component).

    public function debug_setCardInHand(int $cardType, int $playerId) {
        $card = array_values($this->cards->getCardsOfType($cardType))[0];
        $this->cards->moveCard($card['id'], 'hand', $playerId);
    }
    */
}
