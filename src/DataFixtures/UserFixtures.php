<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_REFERENCE = 'user-admin';
    public const BILLETTISTE_REFERENCE = 'user-billettiste';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('l.zerri@gmail.com');
        $admin->setFirstName('Louis');
        $admin->setLastName('Zerri');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password'));
        $manager->persist($admin);
        $this->addReference(self::ADMIN_REFERENCE, $admin);

        $billettiste = new User();
        $billettiste->setEmail('billettiste@les-mathuloire.com');
        $billettiste->setFirstName('Marie');
        $billettiste->setLastName('Martin');
        $billettiste->setRoles(['ROLE_BILLETTISTE']);
        $billettiste->setPassword($this->passwordHasher->hashPassword($billettiste, 'billett123'));
        $manager->persist($billettiste);
        $this->addReference(self::BILLETTISTE_REFERENCE, $billettiste);

        $manager->flush();
    }
}
