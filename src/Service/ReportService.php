<?php

namespace App\Service;

use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class ReportService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private RepresentationRepository $representationRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private DashboardService $dashboardService,
        private SeatPlanPdfGenerator $seatPlanPdfGenerator,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $mailerFrom,
    ) {
    }

    public function sendDailyReport(array $recipientEmails): int
    {
        $seasonStats = $this->dashboardService->getSeasonStats();
        $repStats = $this->dashboardService->getRepresentationStats();

        $upcomingReps = $this->representationRepository->findUpcoming();
        $todayReservations = $this->reservationRepository->findTodayReservations();

        $html = $this->twig->render('email/daily_report.html.twig', [
            'seasonStats' => $seasonStats,
            'repStats' => $repStats,
            'upcomingReps' => $upcomingReps,
            'todayReservations' => $todayReservations,
            'date' => new \DateTime(),
        ]);

        // Générer les plans de salle pour les prochaines représentations
        $seatPlanPdfs = [];
        foreach ($upcomingReps as $rep) {
            $pdf = $this->seatPlanPdfGenerator->generate($rep);
            $filename = sprintf('plan-salle-%s-%s.pdf',
                $rep->getShow()->getTitle(),
                $rep->getDatetime()->format('d-m-Y')
            );
            $seatPlanPdfs[] = ['pdf' => $pdf, 'filename' => $filename];
        }

        $sent = 0;
        foreach ($recipientEmails as $recipient) {
            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($recipient)
                ->subject('Rapport journalier - Les Mathu\'Loire - ' . (new \DateTime())->format('d/m/Y'))
                ->html($html);

            foreach ($seatPlanPdfs as $attachment) {
                $email->attach($attachment['pdf'], $attachment['filename'], 'application/pdf');
            }

            $this->mailer->send($email);
            $sent++;
        }

        return $sent;
    }
}
