<?php

return [
    'singletons' => [
        \Services\AuthService::class => function ($c) {
            return new \Services\AuthService();
        },

        'auth' => function ($c) {
            return $c->get(\Services\AuthService::class);
        },

        \Core\Hooks\HookManager::class => function ($c) {
            $manager = new \Core\Hooks\HookManager();

            $hooksConfigPath = PROJECT_PATH . '/config/hooks.php';
            if (file_exists($hooksConfigPath)) {
                $hooks = require $hooksConfigPath;
                if (is_array($hooks)) {
                    foreach ($hooks as $hookClass) {
                        if (!class_exists($hookClass, true)) {
                            continue;
                        }

                        $implements = class_implements($hookClass);
                        $interfaceName = \Core\Hooks\HookInterface::class;
                        if ($implements && in_array($interfaceName, $implements, true)) {
                            try {
                                $manager->register(new $hookClass());
                            } catch (\Exception $e) {
                                // Хук не должен ронять контейнер
                            }
                        }
                    }
                }
            }

            return $manager;
        },

        'hooks.manager' => function ($c) {
            return $c->get(\Core\Hooks\HookManager::class);
        },

        \Services\RoleService::class => function ($c) {
            return new \Services\RoleService();
        },

        \Services\AnalysisService::class => function ($c) {
            return new \Services\AnalysisService($c->get(\Core\Database\QueryBuilder::class));
        },

        \Services\AnalysisWorkflowService::class => function ($c) {
            return new \Services\AnalysisWorkflowService(
                $c->get(\Core\Database\QueryBuilder::class),
                $c->get(\Services\AnalysisService::class)
            );
        },

        \Services\CodeService::class => function ($c) {
            return new \Services\CodeService($c->get(\Core\Database\QueryBuilder::class));
        },

        \Services\Pdf\RemoteHtmlToPdf::class => function ($c) {
            return \Services\Pdf\RemoteHtmlToPdf::fromConfig($c->get(\Core\Config::class));
        },

        \Services\Pdf\HtmlToPdfConverter::class => function ($c) {
            return $c->get(\Services\Pdf\RemoteHtmlToPdf::class);
        },

        \Services\Pdf\PrintFailureAlert::class => function ($c) {
            return \Services\Pdf\PrintFailureAlert::fromConfig($c->get(\Core\Config::class));
        },

        \Services\Pdf\ConclusionDocument::class => function ($c) {
            return new \Services\Pdf\ConclusionDocument(
                $c->get(\Core\Database\QueryBuilder::class),
                $c->get(\Core\Config::class)
            );
        },

        \Services\Pdf\ConclusionPrint::class => function ($c) {
            return new \Services\Pdf\ConclusionPrint(
                $c->get(\Services\Pdf\ConclusionDocument::class),
                new \Services\Pdf\ConclusionAccess(),
                new \Services\Pdf\ConclusionPrintToken(),
                $c->get(\Services\Pdf\HtmlToPdfConverter::class),
                $c->get(\Services\Pdf\PrintFailureAlert::class),
                $c->get(\Core\Config::class)
            );
        },

        'mailer' => function ($c) {
            return new \Services\MailService();
        },
    ],
];
