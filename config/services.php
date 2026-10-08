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

        'mailer' => function ($c) {
            return new \Services\MailService();
        },
    ],
];
