<?php

namespace App\DataFixtures;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AuditLogFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);
        $billettiste = $this->getReference(UserFixtures::BILLETTISTE_REFERENCE, User::class);

        // Scénario réaliste sur 7 derniers jours
        $entries = [
            // J-6 : admin fait de la config
            ['-6 days 08:02', $admin, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '90.1.34.12'],
            ['-6 days 08:15', $admin, AuditLogger::SHOW_UPDATE, 'Mise à jour du spectacle "Miss Purple mène l\'enquête"', 'Show', 1, '90.1.34.12'],
            ['-6 days 08:42', $admin, AuditLogger::REPRESENTATION_UPDATE, 'Mise à jour représentation #12 (Miss Purple)', 'Representation', 12, '90.1.34.12'],
            ['-6 days 09:10', $admin, AuditLogger::LOGOUT, 'Déconnexion', null, null, '90.1.34.12'],

            // J-5 : tentatives de hack
            ['-5 days 03:12', null, AuditLogger::LOGIN_FAILURE, 'Échec de connexion (admin@admin.com)', null, null, '185.220.101.47'],
            ['-5 days 03:12', null, AuditLogger::LOGIN_FAILURE, 'Échec de connexion (admin@admin.com)', null, null, '185.220.101.47'],
            ['-5 days 03:13', null, AuditLogger::LOGIN_FAILURE, 'Échec de connexion (root@localhost)', null, null, '185.220.101.47'],
            ['-5 days 03:13', null, AuditLogger::LOGIN_FAILURE, 'Échec de connexion (l.zerri@gmail.com)', null, null, '185.220.101.47'],
            ['-5 days 03:13', null, AuditLogger::LOGIN_FAILURE, 'Échec de connexion (l.zerri@gmail.com)', null, null, '185.220.101.47'],

            // J-4 : billettiste travaille sur les réservations
            ['-4 days 10:01', $billettiste, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '78.193.22.4'],
            ['-4 days 10:08', $billettiste, AuditLogger::RESERVATION_CREATE, 'Création manuelle de la réservation #415 (Jean Dupont)', 'Reservation', 415, '78.193.22.4'],
            ['-4 days 10:14', $billettiste, AuditLogger::RESERVATION_CREATE, 'Création manuelle de la réservation #416 (Marie Lefèvre)', 'Reservation', 416, '78.193.22.4'],
            ['-4 days 10:22', $billettiste, AuditLogger::RESERVATION_UPDATE, 'Mise à jour de la réservation #412', 'Reservation', 412, '78.193.22.4'],
            ['-4 days 10:35', $billettiste, AuditLogger::RESERVATION_RESEND_EMAIL, 'Renvoi email réservation #408 à sophie.dupuis@email.com', 'Reservation', 408, '78.193.22.4'],
            ['-4 days 11:48', $billettiste, AuditLogger::LOGOUT, 'Déconnexion', null, null, '78.193.22.4'],

            // J-3 : admin remboursement
            ['-3 days 14:20', $admin, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '90.1.34.12'],
            ['-3 days 14:25', $admin, AuditLogger::RESERVATION_REFUND, 'Remboursement HelloAsso + annulation réservation #389', 'Reservation', 389, '90.1.34.12'],
            ['-3 days 14:32', $admin, AuditLogger::RESERVATION_CANCEL, 'Annulation de la réservation #401', 'Reservation', 401, '90.1.34.12'],
            ['-3 days 14:45', $admin, AuditLogger::LOGOUT, 'Déconnexion', null, null, '90.1.34.12'],

            // J-2 : billettiste crée une représentation supplémentaire
            ['-2 days 09:30', $billettiste, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '78.193.22.4'],
            ['-2 days 09:45', $billettiste, AuditLogger::REPRESENTATION_CREATE, 'Création représentation Pauvre Pêcheur — 21/03/2027 20:30', 'Representation', 22, '78.193.22.4'],
            ['-2 days 09:58', $billettiste, AuditLogger::RESERVATION_CREATE, 'Création manuelle de la réservation #420 (Paul Martin)', 'Reservation', 420, '78.193.22.4'],

            // J-1 : admin gère les utilisateurs
            ['-1 days 16:15', $admin, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '90.1.34.12'],
            ['-1 days 16:18', $admin, AuditLogger::USER_UPDATE, 'Mise à jour utilisateur billettiste@les-mathuloire.com (mot de passe changé)', 'User', 2, '90.1.34.12'],
            ['-1 days 16:22', $admin, AuditLogger::REPRESENTATION_CANCEL, 'Annulation représentation #99 (Gendre Idéal)', 'Representation', 99, '90.1.34.12'],
            ['-1 days 16:30', $admin, AuditLogger::LOGOUT, 'Déconnexion', null, null, '90.1.34.12'],

            // Aujourd'hui : activité récente
            ['-3 hours', $billettiste, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '78.193.22.4'],
            ['-2 hours 55 minutes', $billettiste, AuditLogger::RESERVATION_UPDATE, 'Mise à jour de la réservation #425', 'Reservation', 425, '78.193.22.4'],
            ['-2 hours 40 minutes', $billettiste, AuditLogger::RESERVATION_RESEND_EMAIL, 'Renvoi email réservation #425 à p.bernard@email.com', 'Reservation', 425, '78.193.22.4'],
            ['-1 hours', $admin, AuditLogger::LOGIN_SUCCESS, 'Connexion réussie', null, null, '90.1.34.12'],
            ['-45 minutes', $admin, AuditLogger::SHOW_UPDATE, 'Mise à jour du spectacle "La Chasse de l\'Enfer"', 'Show', 4, '90.1.34.12'],
            ['-20 minutes', $admin, AuditLogger::REPRESENTATION_UPDATE, 'Mise à jour représentation #17 (Gendre Idéal)', 'Representation', 17, '90.1.34.12'],
        ];

        foreach ($entries as [$when, $user, $action, $summary, $type, $targetId, $ip]) {
            $log = new AuditLog();
            $log->setAction($action);
            $log->setSummary($summary);
            $log->setTargetType($type);
            $log->setTargetId($targetId);
            $log->setIpAddress($ip);
            $log->setCreatedAt(new \DateTimeImmutable($when));

            if ($user) {
                $log->setUser($user);
                $log->setUserEmail($user->getEmail());
            } else {
                // Tentatives anonymes : on garde l'email tenté si présent dans le résumé
                $log->setUserEmail('anonymous');
            }

            $manager->persist($log);
        }

        $manager->flush();
    }
}
