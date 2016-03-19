<?php

/**
 * GSManager
 *
 * This is a mighty and platform independent software for administrating game servers of various kinds.
 * If you need help with installing or using this software, please visit our website at: www.GSManager.de
 * If you want to obtain additional permissions extending the license, contact us at: Webmaster@GSManager.de
 *
 * @copyright Greenfield Concept UG (haftungsbeschränkt)
 * @license Creative Commons BY-NC-ND 4.0 <http://www.creativecommons.org/licenses/by-nc-nd/4.0/>
 * @version 1.0.0-RC+1
 * */

namespace GSM\Plugins\Antiwrongguid;

use GSM\Daemon\Core\Utils;

/**
 * Automessages plugin
 *
 * sends auto messages messages all x seconds
 *
 */
class Antiwrongguid extends Utils {

    /**
     * Inits the plugin
     *
     * This function initiates the plugin. This means that it register commands
     * default values, and events. It's important that every plugin has this function
     * Otherwise the plugin exists but can't be used
     */
    public function initPlugin() {
        parent::initPlugin();

        $this->config->setDefault('antiwrongguid', 'enabled', false);
        $this->config->setDefault('antiwrongguid', 'mode', 'kick');
        $this->config->setDefault('antiwrongguid', 'reasonempty', 'Server detected empty GUID');
        $this->config->setDefault('antiwrongguid', 'reasonmulti', 'Multiple Key');
    }

    public function enable() {
        parent::enable();

        $this->events->register("playerJoined", [$this,"playerJoined"]);
        $this->events->register("playerPIDChange", [$this,"playerPIDChange"]);
    }

    public function disable() {
        parent::disable();

        $this->events->unregister("playerJoined", [$this,"playerJoined"]);
        $this->events->unregister("playerPIDChange", [$this,"playerPIDChange"]);
    }

    public function playerJoined($guid) {
        $guid = trim($guid);

        if (!empty($guid) && ctype_xdigit($guid) && strlen($guid) == 32) {
            return;
        } elseif ($guid === 'BOT-Client') {
            return;
        }

        switch ($this->config->get("antiwrongguid", "mode")) {
            case "tempban":
                $this->players[$guid]->tempBan($this->config->get("antiwrongguid", "reasonempty"));
                break;
            case "kick":
                $this->players[$guid]->kick($this->config->get("antiwrongguid", "reasonempty"));
                break;
            case "ban":
                $this->players[$guid]->ban($this->config->get("antiwrongguid", "reasonempty"));
                break;
        }
    }

    public function playerPIDChange($guid, $old_pid, $new_pid) {
        if ($guid === 'BOT-Client') {
            return;
        }

        $this->logging->info("Anti Mutli Guid: Kicking new player and sync playerlist");
        $this->players[$guid]->kick($this->config->get("antiwrongguid", "reasonmulti"));

        $players = $this->rcon->rconPlayerList();
        $to_remove_players = $this->players;
        foreach($players as $player){
            if(!array_key_exists($player["guid"], $this->players)){
                $this->players[$player["guid"]] = new \GSM\Daemon\Engines\Quake3\Player($player["guid"], $player["pid"], $player["name"]);
                $this->events->trigger("playerJoined", [$player["guid"]]);
            }else{
                unset($to_remove_players[$player["guid"]]);
            }
        }
        
        foreach(array_keys($to_remove_players) as $guid){
            unset($this->players[$guid]);
        }
    }

}
