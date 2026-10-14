<?php

namespace Strategy;

use Core\Container;
use Core\Database\QueryBuilder;
use Core\Page\PageRepository;
use Core\Router\RouteMatch;
use Core\Router\RouteStrategy;
use Helpers\Page;

/**
 * Легаси project/logic/index.php: шаблон адреса → страница каталога и параметр из URL.
 * Включены вход в кабинет (/manager/) и паспорт врача.
 * Страницы с GUID анализа — plannedGuidRoutes(), match() их не отдаёт.
 */
class LegacyUrlStrategy implements RouteStrategy
{
    /** Путь страницы Helpers\Page::PACIENT_LIST. */
    public const PACIENT_LIST_URL = '/manager/mant_pacient/';

    public function match(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if ($urlParts === ['manager']) {
            return $this->redirectToPacientList();
        }

        return $this->matchDoctor($urlParts, $repository);
    }

    /**
     * Намечено, не включено. GUID — колонка t_analysis.varc_guid.
     *
     * @return list<array{url:string, page:int, php:string, param:string, note:string}>
     */
    public static function plannedGuidRoutes(): array
    {
        return [
            [
                'url' => '/manager/pacient/{guid}/',
                'page' => Page::PACIENT,
                'php' => 'form/pacient.php',
                'param' => 'analysis_guid',
                'note' => 'Карточка пациента, заполняет врач',
            ],
            [
                'url' => '/manager/analysis/{guid}/',
                'page' => Page::ANALYSIS,
                'php' => 'form/analysis.php',
                'param' => 'analysis_guid',
                'note' => 'Форма анализа, заполняет лаборатория',
            ],
            [
                'url' => '/manager/passport/{guid}/',
                'page' => Page::PASSPORT,
                'php' => 'web/passport.php',
                'param' => 'passport_guid',
                'note' => 'Паспорт анализа',
            ],
            [
                'url' => '/pdf/{guid}/',
                'page' => Page::PDF,
                'php' => 'form/pdf.php',
                'param' => 'analysis_guid',
                'note' => 'PDF анализа',
            ],
        ];
    }

    private function redirectToPacientList(): RouteMatch
    {
        return new RouteMatch([], null, [
            'http_redirect' => self::PACIENT_LIST_URL,
            'http_redirect_code' => 302,
        ]);
    }

    private function matchDoctor(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if (count($urlParts) !== 3 || $urlParts[0] !== 'manager' || $urlParts[1] !== 'doctor') {
            return null;
        }
        if (!preg_match('/^[0-9]+$/', $urlParts[2])) {
            return null;
        }

        $doctorId = (int) $urlParts[2];
        if ($doctorId <= 0) {
            return null;
        }

        $manager = $repository->findBySlug('manager');
        if (!$manager || !$manager->isActive()) {
            return null;
        }

        $page = $repository->findBySlug('doctor', $manager->id);
        if (!$page || $page->id !== Page::DOCTOR) {
            return null;
        }

        $db = Container::getInstance()->get(QueryBuilder::class);
        $db->reset();
        $row = $db->select(['cat'])->from('t_users')->where(['cat' => $doctorId])->limit(1)->getOne();
        if (!$row) {
            return null;
        }

        return new RouteMatch(
            [$manager, $page],
            $page,
            ['doctor_id' => $doctorId],
            ['doctor_id' => $doctorId]
        );
    }
}
