<?php

/*
 * PowerManager : allow to easily create and apply powers during play
 */
class PowerManager extends APP_GameClass
{
  /*
   * powerClasses : for each power Id, the corresponding class name
   *  (see also constant.inc.php)
   */
  public static $classes = [
    APOLLO => 'Apollo',
    ARTEMIS => 'Artemis',
    ATHENA => 'Athena',
    ATLAS => 'Atlas',
    DEMETER => 'Demeter',
    HEPHAESTUS => 'Hephaestus',
    HERMES => 'Hermes',
    MINOTAUR => 'Minotaur',
    PAN => 'Pan',
    PROMETHEUS => 'Prometheus',
    APHRODITE => 'Aphrodite',
    ARES => 'Ares',
    BIA => 'Bia',
    CHAOS => 'Chaos',
    CHARON => 'Charon',
    CHRONUS => 'Chronus',
    CIRCE => 'Circe',
    DIONYSUS => 'Dionysus',
    EROS => 'Eros',
    HERA => 'Hera',
    HESTIA => 'Hestia',
    HYPNUS => 'Hypnus',
    LIMUS => 'Limus',
    MEDUSA => 'Medusa',
    MORPHEUS => 'Morpheus',
    PERSEPHONE => 'Persephone',
    POSEIDON => 'Poseidon',
    SELENE => 'Selene',
    TRITON => 'Triton',
    ZEUS => 'Zeus',
    AEOLUS => 'Aeolus',
    CHARYBDIS => 'Charybdis',
    CLIO => 'Clio',
    EUROPA => 'Europa',
    GAEA => 'Gaea',
    GRAEAE => 'Graeae',
    HADES => 'Hades',
    HARPIES => 'Harpies',
    HECATE => 'Hecate',
    MOERAE => 'Moerae',
    NEMESIS => 'Nemesis',
    SIREN => 'Siren',
    TARTARUS => 'Tartarus',
    TERPSICHORE => 'Terpsichore',
    URANIA => 'Urania',
    ACHILLES => 'Achilles',
    ADONIS => 'Adonis',
    ATALANTA => 'Atalanta',
    BELLEROPHON => 'Bellerophon',
    HERACLES => 'Heracles',
    JASON => 'Jason',
    MEDEA => 'Medea',
    ODYSSEUS => 'Odysseus',
    POLYPHEMUS => 'Polyphemus',
    THESEUS => 'Theseus',
    TYCHE => 'Tyche',
    SCYLLA => 'Scylla',
    CASTOR => 'Castor',
    PROTEUS => 'Proteus',
    ERIS => 'Eris',
    MAENADS => 'Maenads',
    ASTERIA => 'Asteria',
    HIPPOLYTA => 'Hippolyta',
    HYDRA => 'Hydra',
    IRIS => 'Iris',
    NYX => 'Nyx',
    PEGASUS => 'Pegasus',
  ];

  /*
   * TODO
   */
  public static $bannedMatchups = [
    [ATLAS, GAEA],
    [APHRODITE, NEMESIS],
    [APHRODITE, URANIA],
    [ASTERIA, HADES],
    [BIA, NEMESIS],
    [BIA, TARTARUS],
    [CHARON, HECATE],
    [CIRCE, CLIO],
    [CIRCE, EROS],
    [CIRCE, GAEA],
    [CIRCE, GRAEAE],
    [CIRCE, HECATE],
    [CIRCE, JASON],
    [CIRCE, MOERAE],
    [CIRCE, NYX],
    [CIRCE, PROTEUS],
    [CIRCE, TARTARUS],
    // Additional, ban Circle and any hero
    [CIRCE, ACHILLES],
    [CIRCE, ADONIS],
    [CIRCE, ATALANTA],
    [CIRCE, BELLEROPHON],
    [CIRCE, HERACLES],
    [CIRCE, MEDEA],
    [CIRCE, ODYSSEUS],
    [CIRCE, POLYPHEMUS],
    [CIRCE, THESEUS],
    [CLIO, NEMESIS],
    [ERIS, HECATE],
    [ERIS, PERSEPHONE],
    [ERIS, TARTARUS],
    [GAEA, NEMESIS],
    [GAEA, SELENE],
    [GRAEAE, NEMESIS],
    [HADES, PAN],
    [HARPIES, HERMES],
    [HARPIES, MAENADS],
    [HARPIES, TRITON],
    [HECATE, DIONYSUS],
    [HECATE, MEDEA],
    [HECATE, MOERAE],
    [HECATE, SCYLLA],
    [HECATE, NYX],
    [HECATE, TARTARUS],
    [HECATE, THESEUS],
    [HYPNUS, TERPSICHORE],
    [LIMUS, TERPSICHORE],
    [MEDUSA, NEMESIS],
    [MOERAE, NEMESIS],
    [MOERAE, TARTARUS],
    [NEMESIS, TERPSICHORE],
    [NEMESIS, THESEUS],
    [SELENE, GAEA],
    [PERSEPHONE, TRITON], // CF PERSEPHONE
    [PERSEPHONE, ARTEMIS],  // CF PERSEPHONE
    [PERSEPHONE, CHARON],  // CF PERSEPHONE
    [PERSEPHONE, PROMETHEUS],  // CF PERSEPHONE
    [TARTARUS, TERPSICHORE],
  ];


  public $game;
  public $cards;
  public function __construct($game)
  {
    $this->game = $game;

    // Initialize power deck
    $this->cards = self::getNew('module.common.deck');
    $this->cards->init('card');
    $this->cards->autoreshuffle = true;
  }

  /*
   * getPower: factory function to create a power by ID
   */
  public function getPower($powerId, $playerId = null)
  {
    if (!isset(self::$classes[$powerId])) {
      throw new BgaVisibleSystemException("Power $powerId is not implemented ($playerId)");
    }
    return new self::$classes[$powerId]($this->game, $playerId);
  }

  /*
   * getPowers: return all powers (even those not available in this game)
   */
  public function getPowers()
  {
    return array_map(function ($powerId) {
      return $this->getPower($powerId);
    }, array_keys(self::$classes));
  }

  /*
   * getUiData : get all ui data of all powers : id, name, title, text, hero
   */
  public function getUiData()
  {
    $ui = [];
    foreach ($this->getPowers() as $power) {
      $ui[$power->getId()] = $power->getUiData();
    }
    return $ui;
  }

  public function getStatLabels() {
    $labels = [
      0 => ''
    ];
    foreach ($this->getPowers() as $power) {
      $labels[$power->getId()] = $power->getName();
    }
    return $labels;
  }

  /*
   * getPowersInLocation: return all the powers in a given location
   */
  public function getPowersInLocation($location)
  {
    $cards = $this->cards->getCardsInLocation($location);
    return array_values(array_map(function ($card) {
      return $this->getPower($card['type']);
    }, $cards));
  }

  /*
   * getPowerIdsInLocation: return all the power IDs in a given location
   */
  public function getPowerIdsInLocation($location)
  {
    $cards = $this->cards->getCardsInLocation($location);
    return array_values(array_map(function ($card) {
      return intval($card['type']);
    }, $cards));
  }


  /*
   * createCards:
   *   during game setup, create power card
   */
  public function createCards()
  {
    $sql = 'INSERT INTO card (card_type, card_type_arg, card_location, card_location_arg) VALUES ';
    $values = [];
    foreach (array_keys(self::$classes) as $powerId) {
      $values[] = "('$powerId', 0, 'box', 0)";
    }
    self::DbQuery($sql . implode($values, ','));
  }

  /*
   * preparePowers: move supported power cards to the deck
   */
  public function preparePowers()
  {
    $optionPowers = intval($this->game->getGameStateValue('optionPowers'));
    if ($optionPowers == NONE) {
      return 'done';
    }

    // Filter supported powers depending on the number of players and game option
    $nPlayers = $this->game->playerManager->getPlayerCount();
    $powers = array_filter($this->getPowers(), function ($power) use ($nPlayers, $optionPowers) {
      return $power->isSupported($nPlayers, $optionPowers);
    });
    $powerIds = array_values(array_map(function ($power) {
      return $power->getId();
    }, $powers));

    // Additional filtering for QUICK and TOURNAMENT
    $optionSetup = intval($this->game->getGameStateValue('optionSetup'));
    if (($optionSetup == QUICK || $optionSetup == TOURNAMENT) && $optionPowers != GODS_AND_HEROES) {
      $count = $optionSetup == QUICK ? ($optionPowers == GOLDEN_FLEECE? 1 :$nPlayers) : ($nPlayers + 1) * 2;
      $offer = [];
      for ($i = 0; $i < $count; $i++) {
        $offer[] = $powerIds[array_rand($powerIds, 1)];
        Utils::filter($powerIds, function ($power) use ($offer) {
          // Remove the selected powers AND any banned powers
          return !in_array($power, $offer) && !in_array($power, $this->computeBannedIds($offer));
        });
      }
      $powerIds = $offer;
      if (count($powerIds) != $count) {
        throw new BgaVisibleSystemException("Wrong number of powers during setup (expected: $count, actual: " . count($powerIds) . ")");
      }
    }

    if ($optionSetup == QUICK && $optionPowers == GOLDEN_FLEECE) {
      // QUICK: Go to place worker
      $this->prepareGoldenFleece($powerIds[0]);
      return 'placeWorker';
    }
    else if ($optionSetup == QUICK && $optionPowers != GODS_AND_HEROES) {
      // QUICK: Skip building offer
      $this->cards->moveCards($powerIds, 'offer');
      return 'chooseFirstPlayer';
    } else {
      // TOURNAMENT and CUSTOM: Build offer
      $this->cards->moveCards($powerIds, 'deck');
      $this->cards->shuffle('deck');
      return 'offer';
    }
  }


  /*
   * computeBannedIds: is called during fair division setup, whenever a player add/remove an offer
   *    it should return the list of banned powers against current offer
   */
  public function computeBannedIds($mixed = 'offer')
  {
    $powers = is_array($mixed) ? $mixed : $this->getPowerIdsInLocation($mixed);
    $ids = [];
    foreach ($powers as $power) {
      foreach (self::$bannedMatchups as $matchup) {
        if ($matchup[0] == $power) {
          $ids[] = $matchup[1];
        }
        if ($matchup[1] == $power) {
          $ids[] = $matchup[0];
        }
      }
    }
    return $ids;
  }


  /*
   * addOffer:
   *   during fair division setup, player 1 adds a power to the offer
   */
  public function addOffer($powerId)
  {
    // Move the power card to the selection
    $this->cards->moveCard($powerId, 'offer');
    $this->game->notifyAllPlayers('addOffer', '', [
      'powerId' => $powerId,
      'banned' => $this->computeBannedIds()
    ]);
  }

  /*
   * removeOffer:
   *   during fair division setup, player 1 remove a power from the offer
   */
  public function removeOffer($powerId)
  {
    // Move the power card to the deck
    $this->cards->moveCard($powerId, 'deck');
    $this->game->notifyAllPlayers('removeOffer', '', [
      'powerId' => $powerId,
      'banned' => $this->computeBannedIds()
    ]);
  }


  /*
   * getFirstPlayerSuggestion: TODO
   */
  public function getFirstPlayerSuggestion($offer)
  {
    $minOrderAid = 100;
    $minPowerId = 0;
    foreach ($offer as $powerId) {
      $power = $this->getPower($powerId);
      $o = $power->getOrderAid();
      if ($o < $minOrderAid && $o >= 0) {
        $minOrderAid = $o;
        $minPowerId = $powerId;
      }
    }

    return $minPowerId;
  }


  /*
   * setFirstPlayerOffer: set which power will start
   */
  public function setFirstPlayerOffer($powerId)
  {
    $this->cards->moveCard($powerId, 'offer', '1');
  }


  /*
   * getOffer: return all the offer
   */
  public function getOffer()
  {
    return array_values($this->cards->getCardsInLocation('offer'));
  }



  ///////////////////////////////////////
  ///////////////////////////////////////
  /////////    Golden Fleece ////////////
  ///////////////////////////////////////
  ///////////////////////////////////////

  public function prepareGoldenFleece($powerId)
  {
    $power = $this->getPower($powerId);
    $this->game->notifyAllPlayers('ramPowerSet', clienttranslate('Ram figure will grant the power of ${power_name}'),[
      'power_name' => $power->getName(),
      'powerId' => $power->getId(),
      'i18n' => ['power_name']
    ]);
    $this->cards->moveCard($powerId, 'ramCard');
    self::DbQuery("INSERT INTO card (card_type, card_type_arg, card_location, card_location_arg) VALUES ('$powerId', 0, 'ram', 0), ('$powerId', 0, 'ram', 0)");
  }

  public function getGoldenFleecePowerId()
  {
    return empty($this->cards->getCardsInLocation('ramCard')) ? null : array_values($this->cards->getCardsInLocation('ramCard'))[0]['type'];
  }

  public function checkGoldenFleece()
  {
    $ram = $this->game->board->getRam();
    $goldenFleece = $this->getGoldenFleecePowerId();
    $power = $this->game->powerManager->getPower($goldenFleece);
    foreach($this->game->playerManager->getPlayers() as $player){
      $pId = $player->getId();
      $workers = $this->game->board->getPlacedWorkers($pId);
      Utils::filterWorkers($workers, function($worker) use ($ram){
        return $this->game->board->isNeighbour($worker, $ram, '');
      });

      $playerGoldenFleeceCards = array_values($this->cards->getCardsOfTypeInLocation($goldenFleece, null, 'hand', $pId));

      // Neighbouring ram => gain power
      if(count($workers) > 0){
        if(count($playerGoldenFleeceCards) > 0){
          continue; // The player already have the power => keep it
        }

        $cardId = array_values($this->cards->getCardsInLocation('ram'))[0]['id'];
        $this->cards->moveCard($cardId, 'hand', $pId);
        $this->game->log->addAction('powerChanged');
        $this->game->notifyAllPlayers('powersChanged', clienttranslate('Ram figure grants ${player_name} a new power : ${power_name}'), [
          'i18n' => ['power_name'],
          'power_name' => $power->getName(),
          'player_name' => $player->getName(),
          'fplayers' => $this->game->playerManager->getUiData(),
        ]);
      }
      // Not neighbouring => lose power
      else {
        if(count($playerGoldenFleeceCards) == 0){
          continue; // The player don't have the power => nothing to do
        }

        $this->cards->moveCard($playerGoldenFleeceCards[0]['id'], 'ram');
        $this->game->log->addAction('powerChanged');
        $this->game->notifyAllPlayers('powersChanged', clienttranslate('${player_name} lose the ability of ${power_name}'), [
          'i18n' => ['power_name'],
          'power_name' => $power->getName(),
          'player_name' => $this->game->getActivePlayerName(),
          'fplayers' => $this->game->playerManager->getUiData(),
        ]);
      }
    }
  }



  ///////////////////////////////////////
  ///////////////////////////////////////
  /////////    Apply power   ////////////
  ///////////////////////////////////////
  ///////////////////////////////////////
  public function applyPower($methods, $arg)
  {
    if (!is_array($methods)) {
      $methods = [$methods];
    }
    if (!is_array($arg)) {
      $arg = [$arg];
    }

    // First apply current user power(s)
    $playerId = $this->game->getActivePlayerId();
    $player = $this->game->playerManager->getPlayer($playerId);
    foreach ($player->getPowers() as $power) {
      call_user_func_array([$power, $methods[0]], $arg);
    }

    // Then apply oponnents power(s) if needed
    if (count($methods) > 1) {
      foreach ($this->game->playerManager->getOpponents($playerId) as $opponent) {
        foreach ($opponent->getPowers() as $power) {
          call_user_func_array([$power, $methods[1]], $arg);
        }
      }
    }
  }



  /*
   * argChooseFirstPlayer: is called either when the contestant has to choose first player
   *  or when the powers are assigned randomly
   */
  public function argChooseFirstPlayer(&$arg)
  {
    $powers = $arg['powers'];
    foreach ($powers as $powerId) {
      $this->getPower($powerId)->argChooseFirstPlayer($arg);
    }
  }


  /*
   * argPlaceWorker: is called when a player has to place one of its worker
   */
  public function argPlaceWorker(&$arg)
  {
    $this->applyPower(["argPlayerPlaceWorker", "argOpponentPlaceWorker"], [&$arg]);
  }


  ///////////////////////////////////
  ///////////////////////////////////
  /////////    Use Power   //////////
  ///////////////////////////////////
  ///////////////////////////////////

  /*
   * argUsePower: is called when a player may use its power
   */
  public function argUsePower(&$arg)
  {
    $this->applyPower(["argUsePower"], [&$arg]);
  }

  /*
   * usePower: is called when a player want to use its power
   */
  public function usePower($powerId, $action)
  {
    $playerId = $this->game->getActivePlayerId();
    $player = $this->game->playerManager->getPlayer($playerId);
    foreach ($player->getPowers() as $power) {
      if ($power->getId() == $powerId) {
        $power->usePower($action);
      }
    }
  }


  /*
   * stateAfterSkipPower: is called whenever a player used its (non-standard) power
   */
  public function stateAfterSkipPower()
  {
    return $this->getNewState("stateAfterSkipPower", _("Can't figure next state after skip power"));
  }

  /*
   * stateAfterUsePower: is called whenever a player used its (non-standard) power
   */
  public function stateAfterUsePower()
  {
    return $this->getNewState("stateAfterUsePower", _("Can't figure next state after use power"));
  }


  ///////////////////////////////////////
  ///////////////////////////////////////
  /////////    Work argument   //////////
  ///////////////////////////////////////
  ///////////////////////////////////////

  /*
   * argPlayerWork: is called whenever a player is going to do some work (move/build)
   *    apply every player powers that may add new works or make the work skippable
   *    and then apply every opponent powers that may restrict the possible works
   */
  public function argPlayerWork(&$arg, $action)
  {
    $this->applyPower(["argPlayer" . $action, "argOpponent" . $action], [&$arg]);
  }

  /*
   * argPlayerMove: is called whenever a player is going to do some move
   */
  public function argPlayerMove(&$arg)
  {
    $this->argPlayerWork($arg, 'Move');
  }

  /*
   * argPlayerBuild: is called whenever a player is going to do some build
   */
  public function argPlayerBuild(&$arg)
  {
    $this->argPlayerWork($arg, 'Build');
  }



  /////////////////////////////////////
  /////////////////////////////////////
  /////////    Work action   //////////
  /////////////////////////////////////
  /////////////////////////////////////

  /*
   * playerWork: is called whenever a player try to do some work (move/build).
   *    This is called after checking that the work is valid using argPlayerWork.
   *    This should return true if we want to bypass the usual work function:
   *      eg, Appolo can 'switch' instead of 'move'
   */
  public function playerWork($worker, $work, $action)
  {
    // First apply current user power(s)
    $name = "player" . $action;
    $playerId = $this->game->getActivePlayerId();
    $player = $this->game->playerManager->getPlayer($playerId);
    $r = array_map(function ($power) use ($worker, $work, $name) {
      return $power->$name($worker, $work);
    }, $player->getPowers());
    return count($r) > 0 ? max($r) : false;

    // TODO use an opponentMove function ?
  }


  /*
   * playerMove: is called whenever a player is moving
   */
  public function playerMove($worker, $work)
  {
    return $this->playerWork($worker, $work, 'Move');
  }


  /*
   * playerBuild: is called whenever a player is building
   */
  public function playerBuild($worker, $work)
  {
    return $this->playerWork($worker, $work, 'Build');
  }


  /////////////////////////////////////
  /////////////////////////////////////
  ////////   Afterwork hook   /////////
  /////////////////////////////////////
  /////////////////////////////////////

  /*
   * afterWork: is called after each work of each player.
   *  Useful for Harpies, Bia, ...
   */
  public function afterWork($worker, $work, $action)
  {
    $this->applyPower(["afterPlayer" . $action, "afterOpponent" . $action], [$worker, $work]);
  }

  /*
   * afterMove: is called whenever a player just made a move
   */
  public function afterPlayerMove($worker, $work)
  {
    return $this->afterWork($worker, $work, 'Move');
  }

  /*
   * afterBuild: is called whenever a player just built
   */
  public function afterPlayerBuild($worker, $work)
  {
    return $this->afterWork($worker, $work, 'Build');
  }



  /////////////////////////////////////
  /////////////////////////////////////
  ////////   AfterWork state   ////////
  /////////////////////////////////////
  /////////////////////////////////////

  /*
   * getNewState: is called whenever we try to get the new state
   *   - after a work / skip
   *   - at the beggining of the turn
   */
  public function getNewState($method, $msg)
  {
    $playerId = $this->game->getActivePlayerId();
    $player = $this->game->playerManager->getPlayer($playerId);
    $r = array_filter(array_map(function ($power) use ($method) {
      return $power->$method();
    }, $player->getPowers()));
    if (count($r) > 1) {
      throw new BgaUserException($msg);
    }

    if (count($r) == 1) {
      return $r[0];
    } else {
      return null;
    }
  }


  /*
   * stateAfterWork: is called whenever a player has done some work (in a regular way).
   *    This should return null if we want to continue as usual,
   *      or a valid transition name if we want something special.
   */
  public function stateAfterWork($action)
  {
    $name = "stateAfter" . $action;
    return $this->getNewState($name, _("Can't figure next state after action"));
  }

  /*
   * stateAfterMove: is called after a regular move
   */
  public function stateAfterPlayerMove()
  {
    return $this->stateAfterWork('Move');
  }

  /*
   * stateAfterBuild: is called after a regular build
   */
  public function stateAfterPlayerBuild()
  {
    return $this->stateAfterWork('Build');
  }

  /////////////////////////////////////
  /////////////////////////////////////
  //////////  Start/end turn  /////////
  /////////////////////////////////////
  /////////////////////////////////////

  /*
   * TODO
   */
  public function startOfTurn()
  {
    if(intval($this->game->getGameStateValue('optionPowers')) == GOLDEN_FLEECE){
      $this->checkGoldenFleece();
    }
    $this->applyPower(["startPlayerTurn", "startOpponentTurn"], []);
  }

  public function endOfTurn()
  {
    $this->applyPower(["endPlayerTurn", "endOpponentTurn"], []);
  }


  /*
   * stateStartOfTurn: is called at the beginning of the player state.
   */
  public function stateStartOfTurn()
  {
    return $this->getNewState('stateStartOfTurn', _("Can't figure next state at the beginning of the turn"));
  }

  /*
   * stateAfterSkip: is called after a skip
   */
  public function stateAfterSkip()
  {
    return $this->getNewState('stateAfterSkip', _("Can't figure next state after a skip"));
  }


  /*
   * stateStartOfTurn: is called at the end of the player turn.
   */
  public function stateEndOfTurn()
  {
    return $this->getNewState('stateEndOfTurn', _("Can't figure next state at the end of the turn"));
  }

  /////////////////////////////////////
  /////////////////////////////////////
  ///////////    Winning    ///////////
  /////////////////////////////////////
  /////////////////////////////////////

  /*
   * checkWinning: is called after each work.
   *    $arg contains info about whether some player is winning,
   *      and what should the message be in case of win
   *    We first apply current player power that may make it win
   *      with some additionnal winning condition (eg Pan).
   *   Then we apply opponents powers that may do two things:
   *     - restrict a win : eg Aphrodite or Pegasus
   *     - steal a win : eg Moerae
   *     - make an opponent win : eg Chronus
   */
  public function checkWinning(&$arg)
  {
    // First apply current user power(s)
    $playerId = $this->game->getActivePlayerId();
    $player = $this->game->playerManager->getPlayer($playerId);
    foreach ($player->getPowers() as $power) {
      $power->checkPlayerWinning($arg);
    }

    // Then apply oponnents power(s)
    foreach ($this->game->playerManager->getOpponents($playerId) as $opponent) {
      foreach ($opponent->getPowers() as $power) {
        $power->checkOpponentWinning($arg);
      }
    }
  }
}
