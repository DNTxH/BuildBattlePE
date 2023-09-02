<?php

/**
 * Copyright (c) 2023 NurAzliYT
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @auto-license
 */

declare(strict_types=1);

namespace NurAzliYT\BuildBattlePE;

use NurAzliYT\BuildBattlePE\async\BackgroundTaskPool;
use NurAzliYT\BuildBattlePE\async\directory\AsyncDirectoryDelete;
use NurAzliYT\BuildBattlePE\command\BuildBattleCommand;
use NurAzliYT\BuildBattlePE\game\Game;
use NurAzliYT\BuildBattlePE\game\GameManager;
use NurAzliYT\BuildBattlePE\query\QueryManager;
use NurAzliYT\BuildBattlePE\session\SessionManager;
use NurAzliYT\BuildBattlePE\utility\ConfigurationsValidator;
use NurAzliYT\BuildBattlePE\utility\message\LanguageManager;
use NurAzliYT\libSQL\ConnectionPool;
use CortexPE\Commando\PacketHooker;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;

final class BuildBattlePE extends PluginBase
{
    use SingletonTrait {
        setInstance as protected;
        getInstance as protected _getInstance;
    }

    protected ConnectionPool $connectionPool;
    protected GameManager $gameManager;
    protected SessionManager $sessionManager;

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    public function getGameManager(): GameManager
    {
        return $this->gameManager;
    }

    public function getConnectionPool(): ConnectionPool
    {
        return $this->connectionPool;
    }

    protected function onLoad(): void
    {
        @mkdir($this->getDataFolder() . "maps");

        foreach ($this->getResources() as $resource) {
            $this->saveResource($resource->getFilename());
        }

        BuildBattlePE::setInstance($this);
    }

    protected function onEnable(): void
    {
        if (!PacketHooker::isRegistered()) {
            PacketHooker::register($this);
        }

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->connectionPool = new ConnectionPool($this, $this->getConfig()->get("database"));
        $this->gameManager = new GameManager($this);
        $this->sessionManager = new SessionManager($this);

        LanguageManager::init($this, $this->getConfig()->get("language"));
        QueryManager::setIsMySQL($this->getConfig()->get("database")["provider"] === ConnectionPool::DATA_PROVIDER_MYSQL);
        ConfigurationsValidator::validate($this);

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->getServer()->getCommandMap()->register("buildbattlepe", new BuildBattlePECommand($this));

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->gameManager->handleTick()), 20);

        BackgroundTaskPool::getInstance()->submitTask(new AsyncDirectoryDelete(glob($this->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . Game::GAME_WORLD_IDENTIFIER . "*")));

        $this->connectionPool->submit(QueryManager::getTableCreationQuery());
    }

    public static function getInstance(): BuildBattlePE
    {
        return BuildBattlePE::_getInstance();
    }
}
