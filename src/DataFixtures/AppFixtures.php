<?php

namespace App\DataFixtures;

use App\Entity\Command;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    private array $listCommands = [];

    public function load(ObjectManager $manager): void
    {
        $this->loadCommands($manager, 10);
        $this->loadUser($manager, 100);
    }

    /**
     * @throws \Exception
     */
    private function loadUser(ObjectManager $manager, int $number): void
    {
        for ($i = 1; $i <= $number; $i++)
        {
            $user = new User();
            $user->setUsername('Bluedy'.$i);
            $user->setEmail('bluedy'.$i.'@gmail.com');
            $user->setGender(random_int(0, 2));
            $user->setCommand($this->listCommands[array_rand($this->listCommands)]);
            $manager->persist($user);
        }
        $manager->flush();
    }

    private function loadCommands(ObjectManager $manager, int $number): void
    {
        for ($i = 1; $i <= $number; $i++)
        {
            $commands = new Command();
            $commands->setPrefix('$');
            $commands->setName('commands'.$i);
            $commands->setDescription('La description de la commande numéro '.$i);
            $this->listCommands[] = $commands;
            $manager->persist($commands);
        }
        $manager->flush();
    }
}
