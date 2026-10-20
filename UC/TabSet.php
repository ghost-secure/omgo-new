<?php

namespace UC;

/**
 * Переключатель вкладок без скрипта: радио и панели — соседи, видима отмеченная.
 * Подписи вкладок — кнопки над панелью. Заголовок внутри панели к ним не относится.
 */
final class TabSet
{
    /** @var list<array{key: string, label: string, mark: bool}> */
    private array $tabs = [];

    private bool $panelOpen = false;

    public function __construct(private string $name)
    {
    }

    public function add(string $key, string $label, bool $mark = false): void
    {
        $this->tabs[] = ['key' => $key, 'label' => $label, 'mark' => $mark];
    }

    public function begin(): void
    {
        $name = $this->safe($this->name);
        echo '<div class="tab-set d-flex flex-column gap-md">';
        $index = 0;
        foreach ($this->tabs as $tab) {
            $index++;
            echo '<input class="tab-set__input" type="radio" name="' . $name . '" id="' . $name . '-' . $index . '"'
                . ($index === 1 ? ' checked' : '') . '>';
        }
        echo '<div class="tab-set__bar d-flex flex-wrap gap-sm">';
        $index = 0;
        foreach ($this->tabs as $tab) {
            $index++;
            echo '<label class="tab-set__label" for="' . $name . '-' . $index . '">' . $this->safe($tab['label']);
            if ($tab['mark']) {
                echo ' <span class="text-green" aria-hidden="true">✓</span>';
            }
            echo '</label>';
        }
        echo '</div>';
    }

    public function panel(string $key): void
    {
        $index = 1;
        foreach ($this->tabs as $i => $tab) {
            if ($tab['key'] === $key) {
                $index = $i + 1;
                break;
            }
        }
        if ($this->panelOpen) {
            echo '</div>';
        }
        $this->panelOpen = true;
        echo '<div class="tab-set__panel tab-set__panel--' . $index . ' d-flex flex-column gap-md w-full">';
    }

    public function finish(): void
    {
        if ($this->panelOpen) {
            echo '</div>';
            $this->panelOpen = false;
        }
        echo '</div>';
    }

    private function safe(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
