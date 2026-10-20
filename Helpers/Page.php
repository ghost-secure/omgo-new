<?php

namespace Helpers;

/**
 * Страницы кабинета: статические catalog.id.
 * Не путать с Core\Page\Page — тот объект собирает роутер из строки каталога.
 */
class Page
{
    /** /manager/mant_pacient/ — список заявок. */
    public const PACIENT_LIST = 54;

    /** /manager/pacient/{guid}/ — карточка пациента, заполняет врач. */
    public const PACIENT = 57;

    /** /manager/analysis/{guid}/ — форма анализа, заполняет лаборатория. */
    public const ANALYSIS = 58;

    /** /manager/passport/{guid}/ — паспорт анализа. */
    public const PASSPORT = 65;

    /** Легаси catalog.id заключения. Адрес /pdf/{guid}/ эту строку не читает. */
    public const PDF = 69;

    /** /manager/doctor/{id}/ — паспорт врача. */
    public const DOCTOR = 71;
}
