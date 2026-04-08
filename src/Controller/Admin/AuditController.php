<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditController extends AbstractController
{
    #[Route('/', name: 'app_admin_audit_index')]
    public function index(
        Request $request,
        AuditLogRepository $auditLogRepository,
        UserRepository $userRepository,
    ): Response {
        $userId = (int) $request->query->get('user', 0);
        $action = trim((string) $request->query->get('action', ''));
        $fromStr = trim((string) $request->query->get('from', ''));
        $toStr = trim((string) $request->query->get('to', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;

        $user = $userId ? $userRepository->find($userId) : null;
        $actionFilter = $action ?: null;
        $from = $fromStr ? new \DateTime($fromStr . ' 00:00:00') : null;
        $to = $toStr ? new \DateTime($toStr . ' 23:59:59') : null;

        $logs = $auditLogRepository->findByFilters($user, $actionFilter, $from, $to, $page, $limit);
        $total = $auditLogRepository->countByFilters($user, $actionFilter, $from, $to);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin/audit/index.html.twig', [
            'logs' => $logs,
            'users' => $userRepository->findAll(),
            'actions' => $auditLogRepository->findDistinctActions(),
            'currentUser' => $user,
            'currentAction' => $actionFilter,
            'currentFrom' => $fromStr,
            'currentTo' => $toStr,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }
}
