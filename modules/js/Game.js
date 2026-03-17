/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * PreBadBones implementation : © <Vanwesel Etienne> <e.vanwesel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.js
 *
 * PreBadBones user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

/**
 * We create one State class per declared state on the PHP side, to handle all state specific code here.
 * onEnteringState, onLeavingState and onPlayerActivationChange are predefined names that will be called by the framework.
 * When executing code in this state, you can access the args using this.args
 */
class PlaceTrap {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedTrap = null; // piège sélectionné dans le supply
    }

    onEnteringState(args, isCurrentPlayerActive) {
        //console.log('PlaceTrap onEnteringState', args, isCurrentPlayerActive);
        console.log('PlaceTrap args', args);
        console.log('isCurrentPlayerActive', isCurrentPlayerActive);
        if (isCurrentPlayerActive) {
            // Rendre les pièges du supply cliquables
            Object.values(args._private.supplyTraps).forEach(trap => {
                const el = document.getElementById(trap.token_key);
                if (el) {
                    el.classList.add('selectable');
                    el.addEventListener('click', () => this.onSupplyTrapClick(trap, args));
                }
            });

            // Rendre les pièges du board cliquables (pour récupérer)
            Object.values(args._private.boardTraps).forEach(trap => {
                const el = document.getElementById(trap.token_location); // la cellule
                if (el) {
                    el.classList.add('retrievable');
                    el.addEventListener('click', () => this.onBoardTrapClick(trap.token_location));
                }
            });
        }
    }

   /* onUpdateActionButtons(args, isCurrentPlayerActive)  {
        console.log('PlaceTrap onUpdateActionButtons', args, isCurrentPlayerActive);
        if (isCurrentPlayerActive) {
            const playerId = this.bga.players.getCurrentPlayerId();
            const privateArgs = args._private[playerId];
            if (!privateArgs){console.log('privateArgs manquant pour', playerId, args); return;}
            // Rendre les pièges du supply cliquables
            Object.values(args._private.supplyTraps).forEach(trap => {
                const el = document.getElementById(trap.token_key);
                if (el) {
                    el.classList.add('selectable');
                    el.addEventListener('click', () => this.onSupplyTrapClick(trap, args));
                }
            });

            // Rendre les pièges du board cliquables (pour récupérer)
            Object.values(args._private.boardTraps).forEach(trap => {
                const el = document.getElementById(trap.token_location); // la cellule
                if (el) {
                    el.classList.add('retrievable');
                    el.addEventListener('click', () => this.onBoardTrapClick(trap.token_location));
                }
            });
        }
    }*/

    onSupplyTrapClick(trap, args) {
        console.log("TRAP", trap)
        // Désélectionner si déjà sélectionné
        if (this.selectedTrap?.token_key === trap.token_key) {
            this.selectedTrap = null;
            this.clearValidCells();
            return;
        }

        this.selectedTrap = trap;
        this.clearValidCells();

        // Mettre en évidence les cases vides du board
        //console.log("args._private.boardTraps", args._private.boardTraps)
        const occupiedCells = Object.values(args._private.boardTraps).map(t => t.token_location);
        const playerId = trap.token_location.split('_').slice(-1)[0]; // extraire player_id depuis token_location
        console.log("occupiedCells", occupiedCells);
        console.log("playerId", playerId);
        
        for (let x = 1; x <= 5; x++) {
            for (let y = 1; y <= 5; y++) {
                const cellId = `cell_${playerId}_${x}_${y}`;
                if (cellId === `cell_${playerId}_3_3`) continue; // pas sur la tour
                if (occupiedCells.includes(cellId)) continue; // case occupée
                
                const cell = document.getElementById(cellId);
                if (cell) {
                    cell.classList.add('valid-cell');
                    console.log("cellId", cellId);
                    cell.addEventListener('click', () => this.onCellClick(cellId, playerId));
                }
            }
        }
    }

    onCellClick(cellId, playerId) {
        if (!this.selectedTrap) return;
        this.bga.actions.performAction('actPlaceTrap', {
            trapKey: this.selectedTrap.token_key,
            cell: cellId,
            orientation: 0, // TODO: gérer l'orientation plus tard
            playerId : playerId
        });
        this.selectedTrap = null;
        this.clearValidCells();
    }

    onBoardTrapClick(cell) {
        this.bga.actions.performAction('actRetrieveTrap', { cell: cell });
    }

    clearValidCells() {
        document.querySelectorAll('.valid-cell').forEach(cell => {
            cell.classList.remove('valid-cell');
            cell.replaceWith(cell.cloneNode(true));
        });
    }

    onLeavingState() {
        this.selectedTrap = null;
        this.clearValidCells();
        document.querySelectorAll('.selectable, .retrievable').forEach(el => {
            el.classList.remove('selectable', 'retrievable');
            el.replaceWith(el.cloneNode(true));
        });
    }

    onPlayerActivationChange(args, isCurrentPlayerActive) {
        console.log('onPlayerActivationChange:::: args', args);
      //  this.onEnteringState(args, isCurrentPlayerActive);
    }
}

class MoveHero {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    /**
     * This method is called each time we are entering the game state. You can use this method to perform some user interface changes at this moment.
     */
    onEnteringState(args, isCurrentPlayerActive)  {
        if (isCurrentPlayerActive) {
            args._private.validCells.forEach(cellId => {
                const cell = document.getElementById(cellId);
                if (cell) {
                    cell.classList.add('valid-cell');
                    cell.addEventListener('click', () => this.onCellClick(cellId));
                }
            });
        }
    }

    /**
     * This method is called each time we are leaving the game state. You can use this method to perform some user interface changes at this moment.
     */
    onLeavingState(args, isCurrentPlayerActive) {
        document.querySelectorAll('.valid-cell').forEach(cell => {
            cell.classList.remove('valid-cell');
            cell.replaceWith(cell.cloneNode(true)); //permet de supprimer le listeners
        });
    }

    /**
     * This method is called each time the current player becomes active or inactive in a MULTIPLE_ACTIVE_PLAYER state. You can use this method to perform some user interface changes at this moment.
     * on MULTIPLE_ACTIVE_PLAYER states, you may want to call this function in onEnteringState using `this.onPlayerActivationChange(args, isCurrentPlayerActive)` at the end of onEnteringState.
     * If your state is not a MULTIPLE_ACTIVE_PLAYER one, you can delete this function.
     */
    onPlayerActivationChange(args, isCurrentPlayerActive) {
        //this.onEnteringState(args, isCurrentPlayerActive);
    }

    onCellClick(cellId) {
        this.bga.actions.performAction('actMoveHero', { cell: cellId });
    }
}

export class Game {
    constructor(bga) {
        console.log('prebadbones constructor');
        this.bga = bga;

        // Declare the State classes
        this.MoveHero = new MoveHero(this, bga);
        this.bga.states.register('MoveHero', this.MoveHero);
        this.PlaceTrap = new PlaceTrap(this, bga);
        this.bga.states.register('PlaceTrap', this.PlaceTrap);

        // Uncomment the next line to show debug informations about state changes in the console. Remove before going to production!
        // this.bga.states.logger = console.log;
            
        // Here, you can init the global variables of your user interface
        // Example:
        // this.myGlobalValue = 0;
    }
    
    /*
        setup:
        
        This method must set up the game user interface according to current game situation specified
        in parameters.
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
        
        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */
    
    setup( gamedatas ) {
        console.log( "Starting game setup" );
        this.gamedatas = gamedatas;

        // Example to add a div on the game area
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="aid-board"></div>
            <div id="bag"></div>
            <div id="player-tables"></div>
        `);

       //fill the bag
        const color_skeleton = ["blue","pink","green","yellow","red"];
        const entrance_skeleton = ["top","left","right"];
        const bag = document.getElementById("bag");        
        for (let x=0; x<color_skeleton.length; x++) {
            for(let y=0; y<entrance_skeleton.length; y++){
                for (let z=1; z<=12; z++){
                    const cell = document.createElement("div");
                    cell.classList.add("skeleton");
                    cell.classList.add("skeleton_"+entrance_skeleton[y]);
                    cell.classList.add("skeleton_"+color_skeleton[x]+"_"+entrance_skeleton[y]);
                    cell.id = "skeleton_"+color_skeleton[x]+"_"+entrance_skeleton[y]+"_"+z ;
                    bag.appendChild(cell);
                }
            }
        }

        const currentPlayerId = String(this.bga.gameui.player_id);
        const sortedPlayers = Object.values(gamedatas.players).sort((a, b) => {
            if (String(a.id) === String(currentPlayerId)) return -1;
            if (String(b.id) === String(currentPlayerId)) return 1;
            return a.no - b.no; // ordre naturel pour les autres
        });
        
        // Setting up player boards
        Object.values(sortedPlayers).forEach(player => {
            // example of setting up players boards
            this.bga.playerPanels.getElement(player.id).insertAdjacentHTML('beforeend', `
                <span id="energy-player-counter-${player.id}"></span> Energy
            `);
            const counter = new ebg.counter();
            counter.create(`energy-player-counter-${player.id}`, {
                value: player.energy,
                playerCounter: 'energy',
                playerId: player.id
            });

            // example of adding a div for each player
            document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                <fieldset id="player-table-${player.id}" class="player-table" style="border-color:#${player.color};">
                    <legend><strong class="name-player" style="color:#${player.color};">${player.name}</strong></legend>
                    <div class="left">    
                        <div id="zone-${player.id}">
                            <div id="cimetery_${player.id}" class="cimetery"></div> 
                            <div id="gameboard-${player.id}" class="gameboard">
                                <div id="grid-entrance-top-${player.id}" class="grid-entrance-top">
                                    <div></div>
                                    <div id="entrance_skeleton_pink_top_${player.id}" class="entrance-top"></div>
                                    <div id="entrance_skeleton_yellow_top_${player.id}" class="entrance-top"></div>
                                    <div id="entrance_skeleton_red_top_${player.id}" class="entrance-top"></div>
                                    <div id="entrance_skeleton_blue_top_${player.id}" class="entrance-top"></div>
                                    <div id="entrance_skeleton_green_top_${player.id}" class="entrance-top"></div>
                                    <div></div>
                                </div>
                                <div id="board-${player.id}" class="board">
                                    <div id="grid-entrance-left-${player.id}" class="grid-entrance-left">
                                        <div id="entrance_skeleton_green_left_${player.id}" class="entrance-left"></div>
                                        <div id="entrance_skeleton_blue_left_${player.id}" class="entrance-left"></div>
                                        <div id="entrance_skeleton_red_left_${player.id}" class="entrance-left"></div>
                                        <div id="entrance_skeleton_yellow_left_${player.id}" class="entrance-left"></div>
                                        <div id="entrance_skeleton_pink_left_${player.id}" class="entrance-left"></div>
                                    </div>
                                    <div id="grid-board-${player.id}" class="grid-board"></div>
                                    <div id="grid_entrance_skeleton_right_${player.id}" class="grid-entrance-right">
                                        <div id="entrance_skeleton_pink_right_${player.id}" class="entrance-right"></div>
                                        <div id="entrance_skeleton_yellow_right_${player.id}" class="entrance-right"></div>
                                        <div id="entrance_skeleton_red_right_${player.id}" class="entrance-right"></div>
                                        <div id="entrance_skeleton_blue_right_${player.id}" class="entrance-right"></div>
                                        <div id="entrance_skeleton_green_right_${player.id}" class="entrance-right"></div>
                                    </div>
                                </div>
                            </div> 
                            <div id="village-${player.id}" class="village"><div id="grid-village-${player.id}" class="grid-village"></div></div> 
                        </div> 
                    </div>
                    <div class="right">
                        <div id="supply_${player.id}" class="supply"><div id="grid-supply_${player.id}" class="grid-supply"></div></div> 
                    </div>
                </fieldset>
            `);

            const board = document.getElementById("grid-board-"+player.id);
            for (let x=1; x<=5; x++) {
                for (let y=1; y<=5; y++) {
                    const cell = document.createElement("div");
                    cell.classList.add("cell");
                    cell.id = `cell_${player.id}_${x}_${y}`;
                    board.appendChild(cell);
                    // adding hero
                    /*if(x==3 && y==3){
                        const hero = document.createElement("div");
                        hero.classList.add("hero");
                        hero.id = `hero_${player.id}`;
                        cell.appendChild(hero);
                    }*/
                }
            }
                
            // Créer le héros sans l'attacher à une cellule
            const hero = document.createElement("div");
            hero.classList.add("hero");
            hero.id = `hero_${player.id}`;
            document.getElementById('bag').appendChild(hero); // on le met dans le bag temporairement

            const village = document.getElementById("grid-village-"+player.id);
            for (let x=1; x<=5; x++) {
                const cell = document.createElement("div");
                cell.classList.add("cell");
                cell.id = `cell_village_${player.id}_${x}`;
                village.appendChild(cell);
                const house = document.createElement("div");
                house.classList.add("house");
                house.id = `house_${player.id}_${x}`;
                cell.appendChild(house);
            }
            /*
            const supply = document.getElementById("grid-supply-"+player.id);
            for (let x=1; x<=6; x++) {
                const cell = document.createElement("div");
                cell.classList.add("cell");
                cell.id = `cell_supply_${player.id}_${x}`;
                supply.appendChild(cell);

                if(x<3){
                    const trap = document.createElement("div");
                    trap.classList.add("wall");
                    trap.id = `wall_${player.id}_${x}`;
                    cell.appendChild(trap);
                }
                if(x>2 && x<5){
                    const trap = document.createElement("div");
                    trap.classList.add("catapult");
                    trap.id = `catapult_${player.id}_${x}`;
                    cell.appendChild(trap);

                }
                if(x==5){
                    const trap = document.createElement("div");
                    trap.classList.add("dragon");
                    trap.id = `dragon_${player.id}_${x}`;
                    cell.appendChild(trap);

                }
                if(x==6){
                    const trap = document.createElement("div");
                    trap.classList.add("treasure");
                    trap.id = `treasure_${player.id}_${x}`;
                    cell.appendChild(trap);

                }
            }*/
        });
        
        // TODO: Set up your game interface here, according to "gamedatas"
        // Placer les squelettes depuis gamedatas
        Object.values(gamedatas.skeletons).forEach(skeleton => {
            const skeletonEl = document.getElementById(skeleton.token_key);
            const targetEl = document.getElementById(skeleton.token_location);
            if (skeletonEl && targetEl) {
                targetEl.appendChild(skeletonEl);
            }
        });

        
        Object.values(gamedatas.heroes).forEach(hero => {
            const heroEl = document.getElementById(hero.token_key);
            const targetEl = document.getElementById(hero.token_location);
            if (heroEl && targetEl) {
                targetEl.appendChild(heroEl);
            }
        });

        // Dans le fill the bag
        const trap_types = ['wall', 'catapult', 'dragon', 'treasure'];
        Object.values(gamedatas.traps).forEach(trap => {
            const el = document.createElement("div");
            const type = trap.token_key.split('_')[0]; // wall, catapult, dragon, treasure
            el.classList.add(type);
            el.id = trap.token_key;
            bag.appendChild(el);
        });

        Object.values(gamedatas.traps).forEach(trap => {
            const trapEl = document.getElementById(trap.token_key);
            const locationId = trap.token_location.replace('supply_', 'grid-supply_');
            const targetEl = document.getElementById(locationId);
            //console.log('trap', trap.token_key, 'location', trap.token_location, 'locationId', locationId, 'targetEl', targetEl);
            if (trapEl && targetEl) {
                targetEl.appendChild(trapEl);
            }
        });

        // Setup game notifications to handle (see "setupNotifications" method below)
        this.setupNotifications();

        console.log( "Ending game setup" );
    }

    ///////////////////////////////////////////////////
    //// Utility methods
    
    /*
    
        Here, you can defines some utility methods that you can use everywhere in your javascript
        script. Typically, functions that are used in multiple state classes or outside a state class.
    
    */
    /*onUpdateActionButtons(stateName, args)  {
        const stateClass = this.bga.states.getCurrentPlayerStateClass();
        if (stateClass?.onUpdateActionButtons) {
            stateClass.onUpdateActionButtons(args, this.bga.players.isCurrentPlayerActive());
        }
    }*/
    
    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
        setupNotifications:
        
        In this method, you associate each of your game notifications with your local method to handle it.
        
        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your prebadbones.game.php file.
    
    */
    setupNotifications() {
        console.log( 'notifications subscriptions setup' );
        
        // automatically listen to the notifications, based on the `notif_xxx` function on this class. 
        // Uncomment the logger param to see debug information in the console about notifications.
        this.bga.notifications.setupPromiseNotifications({
            // logger: console.log
        });
    }
    
    // TODO: from this point and below, you can write your game notifications handling methods
    async notif_heroMoved(args) {
        const hero = document.getElementById(`hero_${args.player_id}`);
        const targetCell = document.getElementById(args.cell);
        if (hero && targetCell) {
            targetCell.appendChild(hero);
        }
    }
    
    async notif_trapPlaced(args) {
        const trap = document.getElementById(`${args.trapKey}`);
        const targetCell = document.getElementById(args.cell);
        if (trap && targetCell) {
            targetCell.appendChild(trap);
        }
    }
    
    /*
    Example:
    async notif_cardPlayed( args ) {
        // Note: args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
        
        // TODO: play the card in the user interface.
    }
    */
}
