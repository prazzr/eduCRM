<?php

declare(strict_types=1);

namespace EduCRM\Helpers;

/**
 * View Helper
 * Centralized view rendering utilities for common UI patterns
 * 
 * Replaces duplicated patterns:
 * - Alpine.js searchFilter component (8+ files, ~40 lines each)
 * - Select dropdown rendering (10+ files)
 * - Flash message rendering
 * - Error/success message blocks
 * - Avatar/initial rendering
 * 
 * @package EduCRM\Helpers
 * @version 1.0.0
 * @date January 6, 2026
 */
class ViewHelper
{
    /**
     * Render an Alpine.js quick search component
     * Replaces ~40 lines of duplicated HTML across 8+ module files
     * 
     * @param array $data Data array to search
     * @param array $config Configuration options
     * @return string HTML output
     */
    public static function quickSearch(array $data, array $config = []): string
    {
        $defaults = [
            'searchFields' => ['name', 'email', 'phone'],
            'linkPrefix' => 'edit.php?id=',
            'linkIdField' => 'id',
            'placeholder' => 'Quick search...',
            'minLength' => 2,
            'maxResults' => 8,
            'avatarField' => 'name',
            'avatarColors' => 'from-indigo-500 to-purple-500',
            'subtitleFields' => ['email', 'phone'],
            'badgeField' => null,
            'badgeColors' => [],
            'noResultsText' => 'No results found'
        ];

        $config = array_merge($defaults, $config);

        $jsonData = json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT);
        $searchFieldsJson = json_encode($config['searchFields']);

        $badgeTemplate = '';
        if ($config['badgeField']) {
            $badgeColorsJson = json_encode($config['badgeColors']);
            $badgeTemplate = "
                    <span class=\"px-2 py-0.5 rounded text-xs font-bold\"
                          :class=\"" . htmlspecialchars($badgeColorsJson) . "[item.{$config['badgeField']}] || 'bg-gray-100 text-gray-700'\">
                        <span x-text=\"item.{$config['badgeField']}.toUpperCase()\"></span>
                    </span>";
        }

        $subtitleParts = [];
        foreach ($config['subtitleFields'] as $field) {
            $subtitleParts[] = "<span x-text=\"item.{$field} || ''\"></span>";
        }
        $subtitleHtml = implode(' • ', $subtitleParts);

        return <<<HTML
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: {$jsonData},
        searchFields: {$searchFieldsJson},
        minLength: {$config['minLength']},
        maxResults: {$config['maxResults']}
    })' class="relative">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" 
                   x-model="query"
                   @input="search()"
                   @focus="if(query.length >= {$config['minLength']}) showResults = true"
                   @keydown="handleKeydown(\$event)"
                   @keydown.escape="showResults = false"
                   class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                   placeholder="{$config['placeholder']}" 
                   autocomplete="off">
            
            <span x-show="loading" class="spinner text-slate-400"></span>
        </div>
        
        <!-- Search Results Dropdown -->
        <div x-show="showResults && results.length > 0" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.outside="showResults = false"
             class="search-results-container absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-y-auto z-50">
            
            <template x-for="(item, index) in results" :key="item.{$config['linkIdField']}">
                <a :href="'{$config['linkPrefix']}' + item.{$config['linkIdField']}" :data-index="index"
                   @mouseenter="setSelectedIndex(index)"
                   class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                   :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                    <div class="w-9 h-9 bg-gradient-to-br {$config['avatarColors']} rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                         x-text="item.{$config['avatarField']}.charAt(0).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800" x-text="item.{$config['avatarField']}"></div>
                        <div class="text-xs text-slate-500 truncate">
                            {$subtitleHtml}
                        </div>
                    </div>
                    {$badgeTemplate}
                </a>
            </template>
            
            <div x-show="results.length === 0 && query.length >= {$config['minLength']} && !loading" 
                 class="px-4 py-3 text-center text-slate-500 text-sm">
                {$config['noResultsText']}
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Render a select dropdown with options
     * Replaces foreach loops for select options in 10+ files
     * 
     * @param array $items Array of items with id/name or value/label
     * @param string $name Input name attribute
     * @param mixed $selected Currently selected value
     * @param array $config Configuration options
     * @return string HTML select element
     */
    public static function select(
        array $items,
        string $name,
        mixed $selected = null,
        array $config = []
    ): string {
        $defaults = [
            'id' => $name,
            'class' => 'form-control',
            'valueField' => 'id',
            'labelField' => 'name',
            'placeholder' => 'Select...',
            'required' => false,
            'disabled' => false,
            'emptyValue' => ''
        ];

        $config = array_merge($defaults, $config);

        $attrs = [
            'name="' . htmlspecialchars($name) . '"',
            'id="' . htmlspecialchars($config['id']) . '"',
            'class="' . htmlspecialchars($config['class']) . '"'
        ];

        if ($config['required'])
            $attrs[] = 'required';
        if ($config['disabled'])
            $attrs[] = 'disabled';

        $html = '<select ' . implode(' ', $attrs) . '>';
        $html .= '<option value="' . htmlspecialchars((string) $config['emptyValue']) . '">'
            . htmlspecialchars($config['placeholder']) . '</option>';

        foreach ($items as $item) {
            $value = is_array($item) ? $item[$config['valueField']] : $item;
            $label = is_array($item) ? $item[$config['labelField']] : $item;
            $isSelected = ($value == $selected) ? ' selected' : '';

            $html .= '<option value="' . htmlspecialchars((string) $value) . '"' . $isSelected . '>'
                . htmlspecialchars((string) $label) . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Render a select using LookupCacheService
     * 
     * @param \PDO $pdo Database connection
     * @param string $lookupTable Lookup table name (countries, education_levels, etc.)
     * @param string $name Input name attribute
     * @param mixed $selected Currently selected value
     * @param array $config Additional configuration
     * @return string HTML select element
     */
    public static function lookupSelect(
        \PDO $pdo,
        string $lookupTable,
        string $name,
        mixed $selected = null,
        array $config = []
    ): string {
        require_once __DIR__ . '/../services/LookupCacheService.php';

        $lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
        $items = $lookup->getAll($lookupTable);

        return self::select($items, $name, $selected, $config);
    }

    /**
     * Render an alert/message box
     * Replaces inline style blocks for messages
     * 
     * @param string $message Message content
     * @param string $type Alert type (success, warning, danger, info)
     * @param bool $dismissible Whether alert can be dismissed
     * @return string HTML alert element
     */
    public static function alert(string $message, string $type = 'info', bool $dismissible = true): string
    {
        $typeClasses = [
            'success' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
            'warning' => 'bg-amber-50 border-amber-200 text-amber-700',
            'danger' => 'bg-red-50 border-red-200 text-red-700',
            'info' => 'bg-blue-50 border-blue-200 text-blue-700'
        ];

        $classes = $typeClasses[$type] ?? $typeClasses['info'];
        $dismissBtn = $dismissible
            ? '<button type="button" class="ml-auto text-lg font-semibold opacity-50 hover:opacity-100" onclick="this.parentElement.remove()">&times;</button>'
            : '';

        return <<<HTML
<div class="flex items-center gap-3 px-4 py-3 rounded-lg border {$classes} mb-4">
    <span class="flex-1">{$message}</span>
    {$dismissBtn}
</div>
HTML;
    }

    /**
     * Render avatar with initials
     * 
     * @param string $name Full name
     * @param string $size Size class (sm, md, lg)
     * @param string $colors Gradient colors
     * @return string HTML avatar element
     */
    public static function avatar(string $name, string $size = 'md', string $colors = 'from-indigo-500 to-purple-500'): string
    {
        $initials = self::getInitials($name);

        $sizeClasses = [
            'sm' => 'w-8 h-8 text-xs',
            'md' => 'w-10 h-10 text-sm',
            'lg' => 'w-12 h-12 text-base'
        ];

        $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

        return <<<HTML
<div class="{$sizeClass} bg-gradient-to-br {$colors} rounded-full flex items-center justify-center text-white font-bold">
    {$initials}
</div>
HTML;
    }

    /**
     * Get initials from name
     * 
     * @param string $name Full name
     * @param int $length Max initials length
     * @return string Initials
     */
    public static function getInitials(string $name, int $length = 2): string
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
                if (strlen($initials) >= $length)
                    break;
            }
        }

        return $initials ?: '?';
    }

    /**
     * Render a badge/tag
     * 
     * @param string $text Badge text
     * @param string $color Color scheme
     * @return string HTML badge element
     */
    public static function badge(string $text, string $color = 'gray'): string
    {
        $colorClasses = [
            'gray' => 'bg-slate-100 text-slate-700',
            'blue' => 'bg-blue-100 text-blue-700',
            'green' => 'bg-emerald-100 text-emerald-700',
            'red' => 'bg-red-100 text-red-700',
            'orange' => 'bg-orange-100 text-orange-700',
            'purple' => 'bg-purple-100 text-purple-700',
            'yellow' => 'bg-amber-100 text-amber-700'
        ];

        $classes = $colorClasses[$color] ?? $colorClasses['gray'];

        return "<span class=\"px-2 py-0.5 rounded text-xs font-medium {$classes}\">"
            . htmlspecialchars($text) . "</span>";
    }

    /**
     * Render priority badge
     * 
     * @param string $priority Priority level (hot, warm, cold, high, medium, low, urgent)
     * @return string HTML badge element
     */
    public static function priorityBadge(string $priority): string
    {
        $priority = strtolower($priority);

        $config = [
            'hot' => ['🔥 HOT', 'red'],
            'warm' => ['☀️ WARM', 'orange'],
            'cold' => ['❄️ COLD', 'blue'],
            'urgent' => ['⚡ URGENT', 'red'],
            'high' => ['HIGH', 'orange'],
            'medium' => ['MEDIUM', 'yellow'],
            'low' => ['LOW', 'blue']
        ];

        $conf = $config[$priority] ?? ['UNKNOWN', 'gray'];
        return self::badge($conf[0], $conf[1]);
    }

    /**
     * Render status badge
     * 
     * @param string $status Status text
     * @return string HTML badge element
     */
    public static function statusBadge(string $status): string
    {
        $status = strtolower($status);

        $colorMap = [
            'active' => 'green',
            'completed' => 'green',
            'approved' => 'green',
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'processing' => 'blue',
            'rejected' => 'red',
            'cancelled' => 'red',
            'inactive' => 'gray',
            'draft' => 'gray'
        ];

        $color = $colorMap[$status] ?? 'gray';
        $displayText = ucwords(str_replace('_', ' ', $status));

        return self::badge($displayText, $color);
    }

    /**
     * Render confirmation modal script
     * Replaces duplicated confirmDelete functions
     * 
     * @param string $action Action type (delete, archive, etc.)
     * @param string $entityType Entity type for message
     * @return string JavaScript code
     */
    public static function confirmModal(string $action = 'delete', string $entityType = 'record'): string
    {
        $title = ucfirst($action) . ' ' . ucfirst($entityType) . '?';
        $message = "Are you sure you want to {$action} this {$entityType}? This action cannot be undone.";
        $confirmText = 'Yes, ' . ucfirst($action) . ' It';

        return <<<JS
<script>
function confirm{$action}(id) {
    Modal.show({
        type: 'error',
        title: '{$title}',
        message: '{$message}',
        confirmText: '{$confirmText}',
        onConfirm: function () {
            window.location.href = '{$action}.php?id=' + id;
        }
    });
}
</script>
JS;
    }
}
