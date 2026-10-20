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
 * Включены вход в кабинет (/manager/), паспорт врача, форма пациента
 * (/manager/pacient/ без GUID и /manager/pacient/{guid}/), паспорт анализа
 * /manager/passport/{guid}/ и PDF /pdf/{guid}/.
 * Форма лаборатории — plannedGuidRoutes(), match() её не отдаёт.
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

        $pacient = $this->matchPacient($urlParts, $repository);
        if ($pacient !== null) {
            return $pacient;
        }

        $pdf = $this->matchPdf($urlParts, $repository);
        if ($pdf !== null) {
            return $pdf;
        }

        $passport = $this->matchPassport($urlParts, $repository);
        if ($passport !== null) {
            return $passport;
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
                'url' => '/manager/analysis/{guid}/',
                'page' => Page::ANALYSIS,
                'php' => 'form/analysis.php',
                'param' => 'analysis_guid',
                'note' => 'Форма анализа, заполняет лаборатория',
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

    private function matchPacient(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if (count($urlParts) < 2 || $urlParts[0] !== 'manager' || $urlParts[1] !== 'pacient') {
            return null;
        }

        $guid = '';
        if (count($urlParts) === 3) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $urlParts[2]) !== 1) {
                return null;
            }
            $guid = strtolower($urlParts[2]);
        } elseif (count($urlParts) !== 2) {
            return null;
        }

        $manager = $repository->findBySlug('manager');
        if (!$manager || !$manager->isActive()) {
            return null;
        }

        $page = $repository->findBySlug('pacient', $manager->id);
        if (!$page || $page->id !== Page::PACIENT) {
            return null;
        }

        $attributes = $guid === '' ? [] : ['analysis_guid' => $guid];

        return new RouteMatch([$manager, $page], $page, $attributes, $attributes);
    }

    private function matchPdf(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if (count($urlParts) !== 2 || $urlParts[0] !== 'pdf') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $urlParts[1]) !== 1) {
            return null;
        }

        $page = $repository->findBySlug('pdf');
        if (!$page || $page->id !== Page::PDF) {
            return null;
        }

        $attributes = ['analysis_guid' => strtolower($urlParts[1])];

        return new RouteMatch([$page], $page, $attributes, $attributes);
    }

    private function matchPassport(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if (count($urlParts) !== 3 || $urlParts[0] !== 'manager' || $urlParts[1] !== 'passport') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $urlParts[2]) !== 1) {
            return null;
        }

        $manager = $repository->findBySlug('manager');
        if (!$manager || !$manager->isActive()) {
            return null;
        }

        $page = $repository->findBySlug('passport', $manager->id);
        if (!$page || $page->id !== Page::PASSPORT) {
            return null;
        }

        $attributes = ['passport_guid' => strtolower($urlParts[2])];

        return new RouteMatch([$manager, $page], $page, $attributes, $attributes);
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
