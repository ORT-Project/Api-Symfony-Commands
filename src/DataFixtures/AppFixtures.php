<?php

namespace App\DataFixtures;

use App\Entity\Command;
use App\Entity\SUser;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private array $listCommands = [];
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadCommands($manager, 10);
        $this->loadUser($manager, 100);
        $this->loadSUser($manager);
    }

    private function loadUser(ObjectManager $manager, int $number): void
    {
        for ($i = 1; $i <= $number; $i++)
        {
            $user = new User();
            $user->setUsername('Bluedy'.$i);
            $user->setEmail('bluedy'.$i.'@gmail.com');
            $user->setGender(random_int(1, 3));
            $user->setCommand($this->listCommands[array_rand($this->listCommands)]);
            $user->setMoney(random_int(0, 100000));
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

    private function loadSUser(ObjectManager $manager): void
    {
        $sUser = new SUser();
        $sUser->setEmail("bluedy@gmail.com");
        $sUser->setRoles(["ROLE_USER"]);
        $sUser->setPassword($this->userPasswordHasher->hashPassword($sUser, "password"));
        $manager->persist($sUser);

        $aSUser = new SUser();
        $aSUser->setEmail("velgrynd@gmail.com");
        $aSUser->setRoles(["ROLE_ADMIN"]);
        $aSUser->setPassword($this->userPasswordHasher->hashPassword($aSUser, "password"));
        $manager->persist($aSUser);

        $manager->flush();
    }
}
