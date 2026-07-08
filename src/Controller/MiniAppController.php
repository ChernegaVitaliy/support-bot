<?php

namespace App\Controller;

use App\Services\DatabaseService;
use App\Services\Logger;
use App\Services\ReportService;
use App\Services\TelegramService;
use App\Services\TelegramWebAppService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MiniAppController extends AbstractController
{
    private const REPORTS_PER_PAGE = 10;

    public function __construct(
        private TelegramWebAppService $telegramWebApp,
        private DatabaseService $db,
        private ReportService $reportService,
        private TelegramService $telegram,
        private Logger $logger
    ) {
    }

    #[Route('/', name: 'miniapp_index')]
    public function index(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        if ($ctx['is_admin']) {
            $stats = $this->db->getStats();
            $admins = $this->db->getAllAdmins();

            return $this->renderPage('admin.html.twig', $ctx, [
                'stats' => $stats,
                'admins' => $admins,
            ]);
        }

        $myReports = $this->db->query(
            "SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
            true,
            [$ctx['user_id']]
        ) ?: [];

        return $this->renderPage('dashboard_user.html.twig', $ctx, [
            'my_reports' => $myReports,
        ]);
    }

    #[Route('/report/new', name: 'miniapp_report_new', methods: ['GET', 'POST'])]
    public function reportNew(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        if ($request->isMethod('POST')) {
            $reporterNick = trim((string)$request->request->get('reporter_nick', ''));
            $reportedNick = trim((string)$request->request->get('reported_nick', ''));
            $reason = trim((string)$request->request->get('reason', ''));
            $proof = trim((string)$request->request->get('proof', ''));

            if ($reporterNick === '' || $reportedNick === '' || $reason === '') {
                $this->addFlash('error', 'Заповніть усі обовʼязкові поля: нік скаржника, нік порушника та причина.');
                return $this->renderPage('report_new.html.twig', $ctx, [
                    'values' => $request->request->all(),
                ]);
            }

            $session = [
                'user_id' => $ctx['user_id'],
                'data' => [
                    'reporter_nick' => $reporterNick,
                    'reported_nick' => $reportedNick,
                    'reason' => $reason,
                    'media_files' => [],
                    'text_proof' => $proof,
                ],
                'lang' => $ctx['language'],
            ];

            $reportId = $this->reportService->saveReport($session, $ctx['language']);

            if (!$reportId) {
                $this->addFlash('error', 'Не вдалося створити репорт. Спробуйте пізніше.');
                return $this->renderPage('report_new.html.twig', $ctx, [
                    'values' => $request->request->all(),
                ]);
            }

            $this->addFlash('success', "Репорт #{$reportId} успішно створено та надіслано адміністрації.");
            return $this->redirectToRoute('miniapp_report_detail', ['id' => $reportId]);
        }

        return $this->renderPage('report_new.html.twig', $ctx, [
            'values' => [
                'reporter_nick' => $ctx['user']['username'] ? '@' . $ctx['user']['username'] : '',
            ],
        ]);
    }

    #[Route('/reports', name: 'miniapp_reports', methods: ['GET'])]
    public function reports(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        if ($ctx['is_admin']) {
            $status = $request->query->get('status', 'pending');
            if (!in_array($status, ['pending', 'accepted', 'rejected', 'all'], true)) {
                $status = 'pending';
            }
            $page = max(1, (int)$request->query->get('page', 1));
            $offset = ($page - 1) * self::REPORTS_PER_PAGE;

            $reports = $this->db->getReportsPaginated(
                $status === 'all' ? null : $status,
                self::REPORTS_PER_PAGE,
                $offset
            );
            $total = $this->db->getReportsCount($status === 'all' ? null : $status);
            $totalPages = (int)ceil($total / self::REPORTS_PER_PAGE) ?: 1;

            return $this->renderPage('reports_admin.html.twig', $ctx, [
                'reports' => $reports,
                'status' => $status,
                'page' => $page,
                'total_pages' => $totalPages,
                'counts' => [
                    'pending' => $this->db->getReportsCount('pending'),
                    'accepted' => $this->db->getReportsCount('accepted'),
                    'rejected' => $this->db->getReportsCount('rejected'),
                    'all' => $this->db->getReportsCount(),
                ],
            ]);
        }

        $myReports = $this->db->query(
            "SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC",
            true,
            [$ctx['user_id']]
        ) ?: [];

        return $this->renderPage('reports_user.html.twig', $ctx, [
            'reports' => $myReports,
        ]);
    }

    #[Route('/report/{id}', name: 'miniapp_report_detail', methods: ['GET'])]
    public function reportDetail(Request $request, int $id): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        $report = $this->db->getReportById($id);
        if (!$report) {
            $this->addFlash('error', "Репорт #{$id} не знайдено.");
            return $this->redirectToRoute('miniapp_reports');
        }

        $isOwner = $report['user_id'] === $ctx['user_id'];
        if (!$ctx['is_admin'] && !$isOwner) {
            $this->addFlash('error', 'У вас немає доступу до цього репорту.');
            return $this->redirectToRoute('miniapp_reports');
        }

        $mediaFiles = [];
        if (in_array($report['proof_type'], ['multiple_media', 'media'], true) && !empty($report['proof'])) {
            $decoded = json_decode($report['proof'], true);
            if (is_array($decoded)) {
                $mediaFiles = $decoded;
            }
        }

        return $this->renderPage('report_detail.html.twig', $ctx, [
            'report' => $report,
            'media_files' => $mediaFiles,
            'can_process' => $ctx['is_admin'] && $report['status'] === 'pending',
        ]);
    }

    #[Route('/report/{id}/process', name: 'miniapp_report_process', methods: ['POST'])]
    public function reportProcess(Request $request, int $id): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        if (!$ctx['is_admin'] || !$this->hasPermission('moderator', $ctx['rank'])) {
            $this->addFlash('error', 'У вас немає прав для обробки репортів.');
            return $this->redirectToRoute('miniapp_reports');
        }

        $report = $this->db->getReportById($id);
        if (!$report || $report['status'] !== 'pending') {
            $this->addFlash('error', "Репорт #{$id} вже оброблено або не знайдено.");
            return $this->redirectToRoute('miniapp_report_detail', ['id' => $id]);
        }

        $action = $request->request->get('action');
        if (!in_array($action, ['accept', 'reject'], true)) {
            $this->addFlash('error', 'Невідома дія.');
            return $this->redirectToRoute('miniapp_report_detail', ['id' => $id]);
        }

        $adminNotes = trim((string)$request->request->get('admin_notes', ''));
        $newStatus = $action === 'accept' ? 'accepted' : 'rejected';

        $this->db->updateReportStatus($id, $newStatus, $adminNotes ?: null, $ctx['user_id']);

        if (!empty($report['user_id'])) {
            $statusText = $newStatus === 'accept' ? 'прийнято' : 'відхилено';
            $message = "Ваш репорт #{$id} було {$statusText}.";
            if ($adminNotes) {
                $message .= "\nКоментар адміністрації: {$adminNotes}";
            }
            try {
                $this->telegram->sendMessage($report['user_id'], $message);
            } catch (\Exception $e) {
                $this->logger->warning("Не вдалося сповістити користувача про репорт #{$id}: " . $e->getMessage());
            }
        }

        $verb = $newStatus === 'accept' ? 'прийнято' : 'відхилено';
        $this->addFlash('success', "Репорт #{$id} {$verb}.");
        return $this->redirectToRoute('miniapp_report_detail', ['id' => $id]);
    }

    #[Route('/admin', name: 'miniapp_admin', methods: ['GET'])]
    public function admin(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        if (!$ctx['is_admin'] || !$this->hasPermission('moderator', $ctx['rank'])) {
            $this->addFlash('error', 'Доступ заборонено.');
            return $this->redirectToRoute('miniapp_index');
        }

        $stats = $this->db->getStats();
        $admins = $this->db->getAllAdmins();

        return $this->renderPage('admin.html.twig', $ctx, [
            'stats' => $stats,
            'admins' => $admins,
        ]);
    }

    #[Route('/ranks', name: 'miniapp_ranks', methods: ['GET', 'POST'])]
    public function ranks(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        if (!$ctx['is_admin'] || !$this->hasPermission('moderator', $ctx['rank'])) {
            $this->addFlash('error', 'Доступ заборонено.');
            return $this->redirectToRoute('miniapp_index');
        }

        if ($request->isMethod('POST')) {
            $this->handleRankAction($request, $ctx);
            return $this->redirectToRoute('miniapp_ranks');
        }

        $admins = $this->db->getAllAdmins();
        $defaultOwnerId = $this->db->getDefaultOwnerId();

        return $this->renderPage('ranks.html.twig', $ctx, [
            'admins' => $admins,
            'default_owner_id' => $defaultOwnerId,
            'can_set_rank' => $this->hasPermission('owner', $ctx['rank']),
            'can_remove' => $this->hasPermission('admin', $ctx['rank']),
        ]);
    }

    #[Route('/profile', name: 'miniapp_profile', methods: ['GET'])]
    public function profile(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return $this->bootstrapResponse();
        }

        $dbUser = $this->db->getUserById($ctx['user_id']) ?? [];
        $myReportCount = (int)($this->db->query(
            "SELECT COUNT(*) AS c FROM reports WHERE user_id = ?",
            false,
            [$ctx['user_id']]
        )['c'] ?? 0);

        return $this->renderPage('profile.html.twig', $ctx, [
            'db_user' => $dbUser,
            'my_report_count' => $myReportCount,
        ]);
    }

    #[Route('/api/miniapp/test', name: 'miniapp_test', methods: ['POST'])]
    public function testApi(Request $request): JsonResponse
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return new JsonResponse(['error' => 'unauthorized'], 403);
        }

        return new JsonResponse([
            'message' => 'Mini App API is working!',
            'user' => $ctx['user'],
            'is_admin' => $ctx['is_admin'],
            'rank' => $ctx['rank'],
        ]);
    }

    #[Route('/media', name: 'miniapp_media', methods: ['GET'])]
    public function media(Request $request): Response
    {
        $ctx = $this->resolveContext($request);
        if (!$ctx) {
            return new Response('Forbidden', 403);
        }

        $fileId = $request->query->get('file');
        if (empty($fileId)) {
            return new Response('Bad Request', 400);
        }

        $media = $this->telegram->getMediaContent($fileId);
        if (!$media) {
            return new Response('Not Found', 404);
        }

        return new Response($media['content'], 200, [
            'Content-Type' => $media['mime'],
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function handleRankAction(Request $request, array $ctx): void
    {
        $action = $request->request->get('action');
        $identifier = trim((string)$request->request->get('identifier', ''));

        if ($identifier === '') {
            $this->addFlash('error', 'Вкажіть ідентифікатор користувача.');
            return;
        }

        $admin = $this->db->getAdminByIdentifier($identifier);
        if (!$admin) {
            $this->addFlash('error', 'Адміністратора не знайдено.');
            return;
        }

        $currentRankLevel = $this->rankLevel($ctx['rank']);
        $isDefaultOwner = $ctx['user_id'] === $this->db->getDefaultOwnerId();
        $defaultOwnerId = $this->db->getDefaultOwnerId();

        if ($action === 'set_rank') {
            if (!$this->hasPermission('owner', $ctx['rank'])) {
                $this->addFlash('error', 'Лише власник може змінювати ранги.');
                return;
            }

            $newRank = $request->request->get('rank');
            if (!in_array($newRank, ['owner', 'admin', 'moderator'], true)) {
                $this->addFlash('error', 'Невірний ранг.');
                return;
            }

            if ($admin['user_id'] === $defaultOwnerId && $newRank !== 'owner') {
                $this->addFlash('error', 'Неможливо понизити власника.');
                return;
            }

            if ($this->db->setAdminRank($admin['user_id'], $newRank, $ctx['user_id'])) {
                $this->addFlash('success', "Ранг користувача оновлено: {$newRank}.");
            } else {
                $this->addFlash('error', 'Не вдалося оновити ранг.');
            }
            return;
        }

        if ($action === 'remove') {
            if (!$this->hasPermission('admin', $ctx['rank'])) {
                $this->addFlash('error', 'Недостатньо прав для видалення адміністратора.');
                return;
            }

            if ($admin['user_id'] === $defaultOwnerId) {
                $this->addFlash('error', 'Неможливо видалити власника.');
                return;
            }

            if ($admin['user_id'] === $ctx['user_id']) {
                $this->addFlash('error', 'Ви не можете видалити себе.');
                return;
            }

            $targetRankLevel = $this->rankLevel($admin['rank']);
            if (!$isDefaultOwner && $currentRankLevel <= $targetRankLevel) {
                $this->addFlash('error', 'Недостатньо прав для видалення цього адміністратора.');
                return;
            }

            if ($this->db->removeAdmin($admin['user_id'], $ctx['user_id'])) {
                $this->addFlash('success', 'Адміністратора видалено.');
            } else {
                $this->addFlash('error', 'Не вдалося видалити адміністратора.');
            }
            return;
        }

        $this->addFlash('error', 'Невідома дія.');
    }

    private function rankLevel(?string $rank): int
    {
        return match ($rank) {
            'owner' => 3,
            'admin' => 2,
            'moderator' => 1,
            default => 0,
        };
    }

    private function hasPermission(string $requiredRank, ?string $currentRank): bool
    {
        return $this->rankLevel($currentRank) >= $this->rankLevel($requiredRank);
    }

    private function getInitData(Request $request): ?string
    {
        $fromQuery = $request->query->get('initData');
        if (!empty($fromQuery)) {
            return $fromQuery;
        }

        $fromRequest = $request->request->get('initData');
        if (!empty($fromRequest)) {
            return $fromRequest;
        }

        $fromHeader = $request->headers->get('X-Telegram-Init-Data');
        if (!empty($fromHeader)) {
            return $fromHeader;
        }

        return null;
    }

    private function resolveContext(Request $request): ?array
    {
        $initData = $this->getInitData($request);
        if ($initData === null) {
            return null;
        }

        $data = $this->telegramWebApp->validate($initData);
        $tgUser = $this->telegramWebApp->parseUser($data);
        if (!$tgUser) {
            return null;
        }

        $userId = (string)$tgUser['id'];
        $username = $tgUser['username'] ?? null;
        $firstName = $tgUser['first_name'] ?? null;
        $languageCode = $tgUser['language_code'] ?? 'uk';

        $this->db->addUser($userId, $username, $firstName, $languageCode);

        $isAdmin = $this->db->isAdmin($userId);
        $rank = $isAdmin ? $this->db->getAdminRank($userId) : null;

        return [
            'user' => $tgUser,
            'user_id' => $userId,
            'init_data' => $initData,
            'is_admin' => $isAdmin,
            'rank' => $rank,
            'language' => $languageCode,
        ];
    }

    private function renderPage(string $view, array $ctx, array $extra = []): Response
    {
        $data = array_merge($ctx, $extra, [
            'app_user' => $ctx['user'],
            'app_user_id' => $ctx['user_id'],
            'is_admin' => $ctx['is_admin'],
            'rank' => $ctx['rank'],
            'init_data' => $ctx['init_data'],
        ]);

        return $this->render($view, $data);
    }

    private function bootstrapResponse(): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="uk">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Support Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="/css/index.css?v=1">
</head>
<body>
    <div class="w-full max-w-600 mx-auto p-4 pb-0 min-h-screen flex items-center justify-center">
        <div class="text-center py-8 px-4 mb-8 bg-surface rounded border-2 border-border shadow-brutal w-full">
            <div class="w-22 h-22 mx-auto mb-6 rounded bg-surface flex items-center justify-center text-primary border-2 border-border shadow-brutal overflow-hidden shrink-0">
                <i data-lucide="shield-alert" class="w-10 h-10 stroke-1-5"></i>
            </div>
            <h1 class="text-[26px] font-bold mb-3 m-0 font-unbounded tracking-tight uppercase">Support</h1>
            <p class="text-base text-primary mb-6 leading-relaxed font-semibold" id="msg">Завантаження…</p>
        </div>
    </div>
    <script>
        (function () {
            function reloadWithInitData() {
                var initData = '';
                try { initData = window.Telegram.WebApp.initData || ''; } catch (e) {}
                if (initData) {
                    var url = location.pathname + location.search;
                    var sep = url.indexOf('?') !== -1 ? '&' : '?';
                    if (!/[?&]initData=/.test(url)) {
                        url += sep + 'initData=' + encodeURIComponent(initData);
                    }
                    location.replace(url);
                    return true;
                }
                return false;
            }
            if (!reloadWithInitData()) {
                document.getElementById('msg').textContent = 'Відкрийте застосунок у Telegram.';
            }
        })();
    </script>
</body>
</html>
HTML;

        return new Response($html);
    }
}
