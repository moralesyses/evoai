<?php
/**
 * Admin tab "Portal CMS & Pricing" (reference component "Nn").
 *
 * Server-renders the full CMS editor from $cms; assets/js/views/admin-cms.js keeps
 * the edited copy client-side (section filter, list add/remove, two-step reset)
 * and publishes it with admin.cmsUpdate / admin.cmsReset.
 */
$cmsSections = [
    ['id' => 'all', 'label' => 'All Sections', 'icon' => 'layers'],
    ['id' => 'brand', 'label' => 'Brand & Header', 'icon' => 'tag'],
    ['id' => 'hero', 'label' => 'Hero Headlines', 'icon' => 'sparkles'],
    ['id' => 'founder', 'label' => 'Founder & Visionary', 'icon' => 'user'],
    ['id' => 'video', 'label' => 'Keynote Video', 'icon' => 'video'],
    ['id' => 'society', 'label' => 'Society Pillars', 'icon' => 'sliders-vertical'],
    ['id' => 'hardware', 'label' => 'NFC Hardware', 'icon' => 'credit-card'],
    ['id' => 'meetings', 'label' => 'Executive Meetings', 'icon' => 'calendar'],
    ['id' => 'network', 'label' => 'Network & Events', 'icon' => 'globe'],
    ['id' => 'pricing', 'label' => 'Tiers & Pricing', 'icon' => 'award'],
    ['id' => 'cta_footer', 'label' => 'CTA & Footer', 'icon' => 'share-2'],
];
$cmsFilter = (string) ($_GET['section'] ?? 'all');
if (!in_array($cmsFilter, array_column($cmsSections, 'id'), true)) {
    $cmsFilter = 'all';
}
$cmsFilterOn = 'bg-[#f3bd35] text-black font-bold shadow';
$cmsFilterOff = 'text-zinc-400 hover:text-white hover:bg-zinc-900';
$cmsResetOn = 'bg-rose-950/60 border-rose-500 text-rose-200 animate-pulse';
$cmsResetOff = 'bg-zinc-900 border-zinc-700 text-zinc-300 hover:text-white hover:bg-zinc-800';
$cmsIn = 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]';
$cmsLabel = 'block text-[11px] font-semibold text-zinc-300 mb-1';
$cmsLongKeys = ['bio', 'societyDescription', 'educationDescription', 'digitalDescription'];

/** Scalar CMS value as a string (guards against malformed stored data). */
$cmsVal = function (string $key) use ($cms): string {
    $v = $cms[$key] ?? '';
    return is_scalar($v) ? (string) $v : '';
};
/** List CMS value as an array. */
$cmsList = function (string $key) use ($cms): array {
    return is_array($cms[$key] ?? null) ? array_values($cms[$key]) : [];
};

/** Labeled input / textarea bound to one CMS key. */
$cmsField = function (string $key, string $label, array $o = []) use ($cmsVal, $cmsIn, $cmsLabel, $cmsLongKeys): string {
    $id = 'cms-' . $key;
    $type = $o['type'] ?? 'text';
    $class = $o['class'] ?? $cmsIn;
    $max = in_array($key, $cmsLongKeys, true) ? 5000 : 500;
    $html = '<div' . (isset($o['wrap']) ? ' class="' . e($o['wrap']) . '"' : '') . '>';
    $html .= '<label for="' . e($id) . '" class="' . $cmsLabel . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . e($id) . '" name="' . e($key) . '" data-cms-field="' . e($key) . '" rows="' . (int) ($o['rows'] ?? 2) . '" maxlength="' . $max . '" class="' . e($class) . '">' . e($cmsVal($key)) . '</textarea>';
    } elseif ($type === 'number') {
        $html .= '<input id="' . e($id) . '" type="number" name="' . e($key) . '" data-cms-field="' . e($key) . '" data-cms-number value="' . e($cmsVal($key)) . '" min="0" step="1" required class="' . e($class) . '">';
    } else {
        $html .= '<input id="' . e($id) . '" type="' . e($type) . '" name="' . e($key) . '" data-cms-field="' . e($key) . '" value="' . e($cmsVal($key)) . '" maxlength="' . $max . '"' . (!empty($o['required']) ? ' required' : '') . ' class="' . e($class) . '">';
    }
    return $html . '</div>';
};

/** Social URL input (cms.socials.<network>). */
$cmsSocial = function (string $network, string $label, string $placeholder, string $wrap = '') use ($cms, $cmsIn): string {
    $id = 'cms-social-' . $network;
    $socials = is_array($cms['socials'] ?? null) ? $cms['socials'] : [];
    $v = isset($socials[$network]) && is_scalar($socials[$network]) ? (string) $socials[$network] : '';
    return '<div' . ($wrap !== '' ? ' class="' . e($wrap) . '"' : '') . '>'
        . '<label for="' . e($id) . '" class="block text-[10px] text-zinc-400 mb-1">' . e($label) . '</label>'
        . '<input id="' . e($id) . '" type="text" name="socials[' . e($network) . ']" data-cms-social="' . e($network) . '" value="' . e($v) . '" maxlength="500" placeholder="' . e($placeholder) . '" class="' . $cmsIn . '">'
        . '</div>';
};

/* Tag-style string lists (same markup is rendered by admin-cms.js after edits). */
$cmsChipClass = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-900 border border-zinc-700 text-xs text-zinc-200';
$cmsChipGold = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-900 border border-[#f3bd35]/40 text-xs text-[#ffda72]';
$cmsChips = function (string $key, string $chipClass) use ($cmsList): string {
    $html = '<div class="flex flex-wrap gap-2 mb-2" data-cms-list="' . e($key) . '" data-chip-class="' . e($chipClass) . '">';
    foreach ($cmsList($key) as $i => $item) {
        $item = is_scalar($item) ? (string) $item : '';
        $html .= '<span class="' . e($chipClass) . '"><span>' . e($item) . '</span>'
            . '<button type="button" data-cms-remove="' . e($key) . '" data-index="' . $i . '" aria-label="Remove ' . e($item) . '" class="text-zinc-500 hover:text-red-400">' . icon('trash-2', 'w-3 h-3') . '</button></span>';
    }
    return $html . '</div>';
};
/** "New item" input + add button under a tag list. */
$cmsAdder = function (string $key, string $placeholder, string $button, string $btnClass = 'px-4 py-2 bg-zinc-800 hover:bg-[#f3bd35] hover:text-black text-white text-xs font-semibold rounded-xl transition-colors') use ($cmsIn): string {
    return '<div class="flex gap-2">'
        . '<input type="text" data-cms-new="' . e($key) . '" maxlength="200" placeholder="' . e($placeholder) . '" aria-label="' . e($placeholder) . '" class="flex-1 px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white focus:outline-none focus:border-[#f3bd35]">'
        . '<button type="button" data-cms-add="' . e($key) . '" class="' . e($btnClass) . '">' . e($button) . '</button>'
        . '</div>';
};
$cmsCount = function (string $key) use ($cmsList): string {
    return '<span data-cms-count="' . e($key) . '">' . count($cmsList($key)) . '</span>';
};

/* Card-style object lists: pillars {icon,title,desc} and meetingsList {title,desc}. */
$cmsItemVal = function ($item, string $f): string {
    return is_array($item) && isset($item[$f]) && is_scalar($item[$f]) ? (string) $item[$f] : '';
};
$cmsPillarCard = function (int $i, $p) use ($cmsItemVal): string {
    return '<div class="p-3 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2 relative">'
        . '<button type="button" data-cms-remove="pillars" data-index="' . $i . '" class="absolute top-2.5 right-2.5 text-zinc-500 hover:text-red-400 p-1" title="Remove pillar">' . icon('trash-2', 'w-3.5 h-3.5') . '</button>'
        . '<div class="flex items-center gap-2">'
        . '<input type="text" data-cms-item="pillars" data-index="' . $i . '" data-key="icon" value="' . e($cmsItemVal($p, 'icon')) . '" maxlength="200" placeholder="Icon" aria-label="Pillar icon" class="w-12 px-2 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-xs text-center text-white focus:outline-none focus:border-[#f3bd35]">'
        . '<input type="text" data-cms-item="pillars" data-index="' . $i . '" data-key="title" value="' . e($cmsItemVal($p, 'title')) . '" maxlength="200" required placeholder="Pillar Title" aria-label="Pillar title" class="flex-1 px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-xs text-white font-bold focus:outline-none focus:border-[#f3bd35]">'
        . '</div>'
        . '<input type="text" data-cms-item="pillars" data-index="' . $i . '" data-key="desc" value="' . e($cmsItemVal($p, 'desc')) . '" maxlength="200" placeholder="Short description" aria-label="Pillar description" class="w-full px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-[11px] text-zinc-300 focus:outline-none focus:border-[#f3bd35]">'
        . '</div>';
};
$cmsMeetingCard = function (int $i, $m) use ($cmsItemVal): string {
    return '<div class="p-3 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2 relative">'
        . '<button type="button" data-cms-remove="meetingsList" data-index="' . $i . '" class="absolute top-2.5 right-2.5 text-zinc-500 hover:text-red-400 p-1" title="Remove meeting">' . icon('trash-2', 'w-3.5 h-3.5') . '</button>'
        . '<input type="text" data-cms-item="meetingsList" data-index="' . $i . '" data-key="title" value="' . e($cmsItemVal($m, 'title')) . '" maxlength="200" required placeholder="Session Title" aria-label="Session title" class="w-[85%] px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-xs text-white font-bold focus:outline-none focus:border-[#f3bd35]">'
        . '<input type="text" data-cms-item="meetingsList" data-index="' . $i . '" data-key="desc" value="' . e($cmsItemVal($m, 'desc')) . '" maxlength="200" placeholder="Brief session description" aria-label="Session description" class="w-full px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-[11px] text-zinc-300 focus:outline-none focus:border-[#f3bd35]">'
        . '</div>';
};

/** Section card wrapper attributes (filter visibility). */
$cmsSection = function (string $id, string $space = 'space-y-4') use ($cmsFilter): string {
    $visible = $cmsFilter === 'all' || $cmsFilter === $id;
    return 'data-cms-section="' . e($id) . '"' . ($visible ? '' : ' hidden') . ' class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/25 ' . $space . '"';
};
$cmsHead = function (string $iconName, string $title, string $right): string {
    return '<div class="flex items-center justify-between pb-2 border-b border-zinc-800">'
        . '<h4 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-2">' . icon($iconName, 'w-4 h-4') . ' ' . e($title) . '</h4>'
        . $right
        . '</div>';
};
$cmsNote = fn (string $text): string => '<span class="text-[10px] text-zinc-500">' . e($text) . '</span>';
$cmsAddBtn = fn (string $list, string $label): string => '<button type="button" data-cms-add-card="' . e($list) . '" class="flex items-center gap-1 px-2.5 py-1 bg-zinc-800 hover:bg-[#f3bd35] hover:text-black text-xs font-semibold rounded-lg transition-colors text-zinc-200">' . icon('plus', 'w-3.5 h-3.5') . ' ' . e($label) . '</button>';
?>
<div class="max-w-4xl mx-auto space-y-6" id="cms-editor">
    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#17130a] via-[#111111] to-[#0a0a0a] border border-[#f3bd35]/30 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f3bd35]/20 text-[#f3bd35] text-[10px] font-bold tracking-widest uppercase mb-1">
                <?= icon('sliders-vertical', 'w-3.5 h-3.5') ?>
                Management Portal CMS
            </div>
            <h3 class="text-xl font-black text-white">Global Site Content &amp; Pricing Engine</h3>
            <p class="text-xs text-zinc-400 mt-0.5">100% of text, banners, media, pillars, meetings, and pricing on 1EVOAI are editable here in real-time.</p>
        </div>
        <div class="flex items-center gap-2 w-full md:w-auto">
            <button type="button" id="cms-reset" data-on="<?= e($cmsResetOn) ?>" data-off="<?= e($cmsResetOff) ?>" class="flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border transition-all <?= $cmsResetOff ?>">
                <?= icon('rotate-ccw', 'w-3.5 h-3.5 text-amber-400') ?>
                <span data-cms-reset-label>Reset Defaults</span>
            </button>
            <button type="button" id="cms-publish" class="flex-1 md:flex-initial flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-all disabled:opacity-50">
                <?= icon('save', 'w-4 h-4 text-black') ?>
                <span>Save &amp; Publish Live</span>
            </button>
        </div>
    </div>

    <div class="flex items-center gap-1.5 p-1.5 bg-[#101010] border border-[#f3bd35]/20 rounded-2xl overflow-x-auto text-xs" role="tablist" aria-label="CMS sections">
        <?php foreach ($cmsSections as $sec): $secOn = $sec['id'] === $cmsFilter; ?>
            <button type="button" role="tab" aria-selected="<?= $secOn ? 'true' : 'false' ?>" data-cms-filter="<?= e($sec['id']) ?>" data-on="<?= e($cmsFilterOn) ?>" data-off="<?= e($cmsFilterOff) ?>" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-medium whitespace-nowrap transition-all <?= $secOn ? $cmsFilterOn : $cmsFilterOff ?>">
                <?= icon($sec['icon'], 'w-3.5 h-3.5') ?>
                <span><?= e($sec['label']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <form id="cms-form" class="space-y-6" novalidate>
        <div <?= $cmsSection('brand') ?>>
            <?= $cmsHead('tag', '1. Brand Identity & Header Bar', $cmsNote('Global Header & Monogram')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?= $cmsField('brandName', 'Brand Name', ['required' => true]) ?>
                <?= $cmsField('brandTagline', 'Brand Tagline') ?>
                <?= $cmsField('communityBadge', 'Community Badge') ?>
            </div>
        </div>

        <div <?= $cmsSection('hero') ?>>
            <?= $cmsHead('sparkles', '2. Hero Headlines & Call to Actions', $cmsNote('Above-the-fold banner copy')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?= $cmsField('headlinePart1', 'Headline Part 1 (Top)') ?>
                <?= $cmsField('headlinePart2', 'Headline Part 2 (Gold Accent)', ['class' => 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-[#ffda72] font-bold focus:outline-none focus:border-[#f3bd35]']) ?>
                <?= $cmsField('headlinePart3', 'Headline Part 3 (Bottom)') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('heroSubtitle', 'Hero Subtitle') ?>
                <?= $cmsField('heroQuote', 'Hero Quote / Motto') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('heroCtaText', 'Primary Button Text') ?>
                <?= $cmsField('heroSecondaryCtaText', 'Secondary Button Text') ?>
            </div>
        </div>

        <div <?= $cmsSection('founder') ?>>
            <?= $cmsHead('user', '3. Executive Founder Profile & Visionary Section', $cmsNote('Mike Maranan & Bio')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?= $cmsField('name', 'Display Name', ['required' => true]) ?>
                <?= $cmsField('title', 'Executive Title') ?>
                <?= $cmsField('company', 'Company / Group Name') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('location', 'Headquarters / Location') ?>
                <?= $cmsField('avatarUrl', 'Avatar Image URL') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?= $cmsField('phone', 'Mobile / Direct Phone') ?>
                <?= $cmsField('email', 'Official Email', ['type' => 'email']) ?>
                <?= $cmsField('whatsapp', 'WhatsApp Direct') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-zinc-800">
                <?= $cmsField('visionaryKicker', 'Visionary Section Kicker') ?>
                <?= $cmsField('visionaryTitle', 'Visionary Section Title') ?>
            </div>
            <?= $cmsField('visionaryImageUrl', 'Visionary Image / Photo URL') ?>
            <?= $cmsField('bio', 'Executive Biography (Full Story)', ['type' => 'textarea', 'rows' => 4]) ?>
        </div>

        <div <?= $cmsSection('video') ?>>
            <?= $cmsHead('video', '4. Featured Executive Keynote Video', $cmsNote('YouTube embed or video stream')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('featuredVideoKicker', 'Video Kicker') ?>
                <?= $cmsField('featuredVideoTitle', 'Video Title') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('featuredVideoUrl', 'YouTube Video Embed URL') ?>
                <?= $cmsField('featuredVideoThumbnailUrl', 'Video Cover Thumbnail Image URL') ?>
            </div>
        </div>

        <div <?= $cmsSection('society') ?>>
            <?= $cmsHead('sliders-vertical', '5. Executive Society Pillars', $cmsAddBtn('pillars', 'Add Pillar')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('societyKicker', 'Society Section Kicker') ?>
                <?= $cmsField('societyTitle', 'Society Section Title') ?>
            </div>
            <?= $cmsField('societyDescription', 'Society Description', ['type' => 'textarea', 'rows' => 2]) ?>
            <div class="space-y-3 pt-2">
                <label class="block text-[11px] font-bold text-zinc-300">Pillars Grid Cards (<?= $cmsCount('pillars') ?>)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-cms-cards="pillars"><?php foreach ($cmsList('pillars') as $i => $p) { echo $cmsPillarCard($i, $p); } ?></div>
            </div>
        </div>

        <div <?= $cmsSection('hardware') ?>>
            <?= $cmsHead('credit-card', '6. Hardware & Identity (NFC Smart Card)', $cmsNote('NFC Tap Technology Spec')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('digitalKicker', 'Digital Section Kicker') ?>
                <?= $cmsField('digitalTitle', 'Digital Section Title') ?>
            </div>
            <?= $cmsField('digitalDescription', 'Digital Hardware Description', ['type' => 'textarea', 'rows' => 2]) ?>
            <?= $cmsField('digitalImageUrl', 'NFC Card Image URL') ?>
            <div class="space-y-2 pt-2">
                <label class="block text-[11px] font-bold text-zinc-300">Hardware &amp; Software Features List (<?= $cmsCount('digitalFeatures') ?>)</label>
                <?= $cmsChips('digitalFeatures', $cmsChipClass) ?>
                <?= $cmsAdder('digitalFeatures', 'New feature (e.g. Dynamic NFC vCard)...', 'Add Feature') ?>
            </div>
        </div>

        <div <?= $cmsSection('meetings') ?>>
            <?= $cmsHead('calendar', '7. Weekly Executive Meetings', $cmsAddBtn('meetingsList', 'Add Meeting')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('meetingsKicker', 'Meetings Kicker') ?>
                <?= $cmsField('meetingsTitle', 'Meetings Title') ?>
            </div>
            <div class="space-y-3 pt-2">
                <label class="block text-[11px] font-bold text-zinc-300">Scheduled Circles &amp; Sessions (<?= $cmsCount('meetingsList') ?>)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-cms-cards="meetingsList"><?php foreach ($cmsList('meetingsList') as $i => $m) { echo $cmsMeetingCard($i, $m); } ?></div>
            </div>
        </div>

        <div <?= $cmsSection('network', 'space-y-5') ?>>
            <?= $cmsHead('globe', '8. Member Network, Exclusive Events & Education', $cmsNote('Cross-industry syndicate')) ?>
            <div class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?= $cmsField('networkKicker', 'Network Kicker') ?>
                    <?= $cmsField('networkTitle', 'Network Title') ?>
                </div>
                <?= $cmsField('networkDescription', 'Network Description') ?>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-zinc-300">Industries Represented Tags (<?= $cmsCount('industries') ?>)</label>
                    <?= $cmsChips('industries', $cmsChipClass) ?>
                    <?= $cmsAdder('industries', 'Add industry (e.g. Clean Energy, AI Infrastructure)...', 'Add Industry') ?>
                </div>
            </div>
            <div class="pt-4 border-t border-zinc-800 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?= $cmsField('eventsKicker', 'Events Kicker') ?>
                    <?= $cmsField('eventsTitle', 'Events Title') ?>
                </div>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-zinc-300">Exclusive Events Tags (<?= $cmsCount('eventsList') ?>)</label>
                    <?= $cmsChips('eventsList', $cmsChipClass) ?>
                    <?= $cmsAdder('eventsList', 'Add event (e.g. Monaco Yacht Syndicate, Private Dinners)...', 'Add Event') ?>
                </div>
            </div>
            <div class="pt-4 border-t border-zinc-800 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?= $cmsField('educationKicker', 'Education Kicker') ?>
                    <?= $cmsField('educationTitle', 'Education Title') ?>
                </div>
                <?= $cmsField('educationDescription', 'Education Description', ['type' => 'textarea', 'rows' => 2]) ?>
            </div>
        </div>

        <div <?= $cmsSection('pricing', 'space-y-6') ?>>
            <?= $cmsHead('award', '9. Membership Tiers & Real-Time Pricing', $cmsNote('Tier Pricing in PHP (₱)')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('membershipKicker', 'Section Kicker') ?>
                <?= $cmsField('membershipTitle', 'Section Title') ?>
            </div>
            <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-[#ffda72] uppercase tracking-wide">Tier 1: Executive Member</span>
                    <span class="text-[10px] text-zinc-400">Standard Tier</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?= $cmsField('executiveTierName', 'Tier Name', ['required' => true]) ?>
                    <?= $cmsField('executiveSubtitle', 'Subtitle') ?>
                    <?= $cmsField('executivePrice', 'Fee (PHP ₱)', ['type' => 'number', 'class' => 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-[#ffda72] font-mono font-bold focus:outline-none focus:border-[#f3bd35]']) ?>
                </div>
                <?= $cmsField('executiveDescription', 'Description') ?>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-zinc-300">Executive Perks List (<?= $cmsCount('executivePerks') ?>)</label>
                    <?= $cmsChips('executivePerks', $cmsChipClass) ?>
                    <?= $cmsAdder('executivePerks', 'Add perk (e.g. Access to Private Deal Room)...', 'Add Perk') ?>
                </div>
            </div>
            <div class="p-4 rounded-2xl bg-gradient-to-b from-[#1b160b] to-[#121008] border-2 border-[#f3bd35]/60 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-[#ffda72] uppercase tracking-wide">Tier 2: Chairman's Circle (VIP Founder Tier)</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-[#f3bd35] text-black font-extrabold uppercase">Flagship</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?= $cmsField('chairmansTierName', 'Tier Name', ['required' => true]) ?>
                    <?= $cmsField('chairmansSubtitle', 'Subtitle') ?>
                    <?= $cmsField('chairmansPrice', 'Fee (PHP ₱)', ['type' => 'number', 'class' => 'w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-[#ffda72] font-mono font-bold focus:outline-none focus:border-[#f3bd35]']) ?>
                </div>
                <?= $cmsField('chairmansDescription', 'Description') ?>
                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-[#ffda72]">Chairman VIP Perks List (<?= $cmsCount('chairmansPerks') ?>)</label>
                    <?= $cmsChips('chairmansPerks', $cmsChipGold) ?>
                    <?= $cmsAdder('chairmansPerks', 'Add VIP perk (e.g. VIP Concierge Support & Verified Badge)...', 'Add VIP Perk', 'px-4 py-2 bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-bold rounded-xl transition-colors') ?>
                </div>
            </div>
        </div>

        <div <?= $cmsSection('cta_footer') ?>>
            <?= $cmsHead('share-2', '10. Final Call to Action, Footer & Social Links', $cmsNote('Footer closing & social media links')) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('finalCtaKicker', 'Final CTA Kicker') ?>
                <?= $cmsField('finalCtaTitle', 'Final CTA Title') ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $cmsField('finalCtaDescription', 'Final CTA Description') ?>
                <?= $cmsField('finalCtaButtonText', 'Final CTA Button Text') ?>
            </div>
            <?= $cmsField('footerText', 'Footer Copyright & Slogan Text') ?>
            <div class="pt-3 border-t border-zinc-800 space-y-3">
                <label class="block text-[11px] font-bold text-[#f3bd35] uppercase">Official Social Media Channels</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?= $cmsSocial('facebook', 'Facebook URL', 'https://facebook.com/...') ?>
                    <?= $cmsSocial('instagram', 'Instagram URL', 'https://instagram.com/...') ?>
                    <?= $cmsSocial('linkedin', 'LinkedIn URL', 'https://linkedin.com/in/...') ?>
                    <?= $cmsSocial('twitter', 'X (Twitter) URL', 'https://twitter.com/...') ?>
                    <?= $cmsSocial('telegram', 'Telegram Channel / User', 'https://t.me/...', 'sm:col-span-2') ?>
                </div>
            </div>
        </div>

        <div class="sticky bottom-4 z-30 p-3 rounded-2xl bg-[#0c0c0c]/90 backdrop-blur-md border border-[#f3bd35]/40 shadow-2xl flex items-center justify-between gap-3">
            <div class="text-xs text-zinc-400 hidden sm:block" aria-live="polite">
                <span data-cms-saved hidden class="text-emerald-400 font-bold flex items-center gap-1"><?= icon('check', 'w-3.5 h-3.5') ?> All edits are live on the 1EVOAI digital card!</span>
                <span data-cms-ready>Ready to publish changes across the entire 1EVOAI portal.</span>
            </div>
            <button type="submit" id="cms-submit" class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                <?= icon('save', 'w-4 h-4 text-black') ?>
                <span>Save &amp; Publish Live to Card</span>
            </button>
        </div>
    </form>
</div>
