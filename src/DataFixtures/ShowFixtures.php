<?php

namespace App\DataFixtures;

use App\Entity\Show;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ShowFixtures extends Fixture
{
    public const MISS_PURPLE = 'show-miss-purple';
    public const GENDRE_IDEAL = 'show-gendre-ideal';
    public const PAUVRE_PECHEUR = 'show-pauvre-pecheur';
    public const CHASSE_ENFER = 'show-chasse-enfer';

    public function load(ObjectManager $manager): void
    {
        $missPurple = new Show();
        $missPurple->setTitle('Miss Purple mène l\'enquête');
        $missPurple->setDescription('Comédie policière de boulevard en 4 actes.');
        $missPurple->setSynopsis('Violette, une ancienne gendarme surnommée Miss Purple, rend visite à sa tante dans la maison de retraite « Les Glycines ». Elle y découvre que le directeur a été assassiné. Ni une ni deux, elle reprend du service et mène l\'enquête avec l\'aide des résidents aussi excentriques qu\'attachants et du personnel de l\'établissement. Entre quiproquos, faux-semblants et révélations surprenantes, Miss Purple va devoir démêler le vrai du faux pour démasquer le coupable...');
        $missPurple->setAuthor('Dominique Eulalie');
        $missPurple->setDirector('Mi-Claude Beziau');
        $missPurple->setDuration('1h30 avec entracte');
        $missPurple->setImageName('2023-2024-Miss-Purple-Affiche-A4-V1-WEB-644x906.png');
        $manager->persist($missPurple);
        $this->addReference(self::MISS_PURPLE, $missPurple);

        $gendreIdeal = new Show();
        $gendreIdeal->setTitle('Un gendre idéal');
        $gendreIdeal->setDescription('Comédie de boulevard en 2 actes.');
        $gendreIdeal->setSynopsis('Simon, avocat renommé et fortuné, coule des jours tranquilles avec son épouse Rebecca dans leur propriété « Les Glycines ». Leur existence paisible est bouleversée lorsque leur fille Alicia les appelle pour leur annoncer une nouvelle stupéfiante : elle va épouser un inconnu dans trois jours. Cette annonce inattendue va perturber leur vie bien ordonnée et créer le mystère sur les heures à venir...');
        $gendreIdeal->setAuthor('Yves Billot');
        $gendreIdeal->setDirector('Mi-Claude Beziau');
        $gendreIdeal->setDuration('1h30 avec entracte');
        $gendreIdeal->setImageName('Un-gendre-ideal-_-V2-644x906.png');
        $manager->persist($gendreIdeal);
        $this->addReference(self::GENDRE_IDEAL, $gendreIdeal);

        $pauvrePecheur = new Show();
        $pauvrePecheur->setTitle('Pauvre Pêcheur');
        $pauvrePecheur->setDescription('Comédie de boulevard en 3 actes.');
        $pauvrePecheur->setSynopsis('Robert et Yvonne, un vieux couple, passent leur temps à se chamailler. Robert a une idée : disparaître pendant deux jours pour choquer sa femme et la faire changer de comportement. Il parie même ses économies avec son voisin qu\'elle le cherchera désespérément. Mais son plan va prendre une tournure totalement inattendue...');
        $pauvrePecheur->setAuthor('Anny Lescalier');
        $pauvrePecheur->setDirector('Mi-Claude Beziau');
        $pauvrePecheur->setDuration('1h30 avec entracte');
        $pauvrePecheur->setImageName('Pauvre_Pecheur_2023_Annulation-644x857.gif');
        $manager->persist($pauvrePecheur);
        $this->addReference(self::PAUVRE_PECHEUR, $pauvrePecheur);

        $chasseEnfer = new Show();
        $chasseEnfer->setTitle('Chasse en Enfer');
        $chasseEnfer->setDescription('Comédie de boulevard en 3 actes.');
        $chasseEnfer->setSynopsis('Robert et Félix, deux chasseurs invétérés, louent une chambre au « Clos des Cerfs », une vieille ferme tenue par Germaine, dans les Ardennes. Les deux compères ne se doutent pas de ce qui les attend. Les 8 personnages hauts en couleur vont à coup sûr vous faire mourir de rire !');
        $chasseEnfer->setAuthor('Charles Istace');
        $chasseEnfer->setDirector('Mi-Claude Beziau');
        $chasseEnfer->setDuration('1h30 avec entracte');
        $chasseEnfer->setImageName('Printoclock_Affiche-30-x-40-A3_v1-484x644.jpg');
        $manager->persist($chasseEnfer);
        $this->addReference(self::CHASSE_ENFER, $chasseEnfer);

        $manager->flush();
    }
}
