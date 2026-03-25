<?php

declare(strict_types=1);

namespace EnchantsUI;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;
use jojoe77777\FormAPI\SimpleForm;

class Main extends PluginBase {

    private array $selectedItem = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player) return true;

        if($command->getName() === "enchants"){
            $this->openItemMenu($sender);
        }
        return true;
    }

    private function isEnchantable(Item $item): bool {
        return $item instanceof Tool || $item instanceof Armor;
    }

    private function openItemMenu(Player $player): void {
        $config = $this->getConfig();

        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;

            $inv = $player->getInventory()->getContents();
            $items = array_values(array_filter($inv, fn($i) => $this->isEnchantable($i)));

            if(!isset($items[$data])) return;

            $this->selectedItem[$player->getName()] = $items[$data];
            $this->confirmItem($player);
        });

        $form->setTitle($config->get("titles")["item-menu"]);

        $items = array_filter($player->getInventory()->getContents(), fn($i) => $this->isEnchantable($i));

        if(empty($items)){
            $player->sendMessage($config->get("messages")["no-items"]);
            return;
        }

        foreach($items as $item){
            $form->addButton($item->getName());
        }

        $form->addButton($config->get("settings")["close-button"]);

        $player->sendForm($form);
    }

    private function confirmItem(Player $player): void {
        $config = $this->getConfig();

        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;

            if($data === 0){
                $this->openEnchantMenu($player);
            } else {
                $player->sendMessage($this->getConfig()->get("messages")["cancelled"]);
            }
        });

        $form->setTitle($config->get("titles")["confirm-menu"]);
        $form->setContent($config->get("messages")["confirm-item"]);
        $form->addButton("§aYes");
        $form->addButton("§cNo");

        $player->sendForm($form);
    }

    private function openEnchantMenu(Player $player): void {
        $config = $this->getConfig();

        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;

            $enchants = array_keys($this->getConfig()->get("enchants"));
            if(!isset($enchants[$data])) return;

            $this->confirmEnchant($player, $enchants[$data]);
        });

        $form->setTitle($config->get("titles")["enchant-menu"]);

        foreach($config->get("enchants") as $name => $data){
            foreach($data["levels"] as $level => $cost){
                $form->addButton(ucfirst($name) . " " . $level . " (§e{$cost} XP§r)");
            }
        }

        $player->sendForm($form);
    }

    private function confirmEnchant(Player $player, string $enchantName): void {
        $config = $this->getConfig();

        $levels = $config->get("enchants")[$enchantName]["levels"];

        foreach($levels as $level => $cost){
            $form = new SimpleForm(function(Player $player, $data) use ($enchantName, $level, $cost){
                if($data === null) return;

                if($data === 0){
                    $this->applyEnchant($player, $enchantName, $level, $cost);
                } else {
                    $player->sendMessage($this->getConfig()->get("messages")["cancelled"]);
                }
            });

            $form->setTitle($config->get("titles")["cost-menu"]);
            $form->setContent(str_replace("{cost}", (string)$cost, $config->get("messages")["confirm-enchant"]));
            $form->addButton("§aConfirm");
            $form->addButton("§cCancel");

            $player->sendForm($form);
            return;
        }
    }

    private function applyEnchant(Player $player, string $enchantName, int $level, int $cost): void {
        if(!$player->hasPermission("enchantsui.bypass")){
            if($player->getXpManager()->getXpLevel() < $cost){
                $player->sendMessage($this->getConfig()->get("messages")["not-enough-xp"]);
                return;
            }
            $player->getXpManager()->subtractXpLevels($cost);
        }

        $item = $this->selectedItem[$player->getName()] ?? null;
        if($item === null) return;

        $enchant = match($enchantName){
            "efficiency" => VanillaEnchantments::EFFICIENCY(),
            "sharpness" => VanillaEnchantments::SHARPNESS(),
            "protection" => VanillaEnchantments::PROTECTION(),
            default => null
        };

        if($enchant === null) return;

        $item->addEnchantment(new EnchantmentInstance($enchant, $level));
        $player->getInventory()->addItem($item);

        $player->sendMessage($this->getConfig()->get("messages")["success"]);
    }
}
