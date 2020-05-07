<?php

class Atlas extends Power
{
  public static function getId() {
    return ATLAS;
  }

  public static function getName() {
    return clienttranslate('Atlas');
  }

  public static function getTitle() {
    return clienttranslate('Titan Shouldering the Heavens');
  }

  public static function getText() {
    return [
      clienttranslate("Your Build: Your Worker may build a dome at any level.")
    ];
  }

  public static function getPlayers() {
    return [2, 3, 4];
  }

  public static function getBannedIds() {
    return [GAEA];
  }

  public static function isGoldenFleece() {
    return true; 
  }

  /* * */

}
  