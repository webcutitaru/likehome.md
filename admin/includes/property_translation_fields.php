<?php

declare(strict_types=1);

/** @var array<string, array{title: string, slug: string, description: string, description_long: string}> $lhPropertyTranslations */
$lhPropertyTranslations = $lhPropertyTranslations ?? [];
?>
<div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
    <h3 class="text-lg font-bold text-slate-800 border-b pb-4 flex items-center gap-2 uppercase tracking-tighter">
        <i data-lucide="languages" class="text-cta"></i> Traduceri site (EN / RU)
    </h3>
    <p class="text-xs text-slate-500 font-medium leading-relaxed">
        Română = câmpurile de mai sus (titlu + descriere). Aici completezi variantele pentru
        <strong>/en/</strong> și <strong>/ru/</strong>. Lasă gol dacă nu există traducere — site-ul afișează textul RO.
        Adresa rămâne aceeași în toate limbile.
    </p>

    <?php foreach (['en' => 'English', 'ru' => 'Русский'] as $loc => $locLabel):
        $tr = $lhPropertyTranslations[$loc] ?? ['title' => '', 'slug' => '', 'description_long' => ''];
        $pfx = 'tr_' . $loc . '_';
    ?>
    <div class="rounded-[1.75rem] border border-slate-100 bg-slate-50/80 p-6 space-y-4">
        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Titlu (<?= strtoupper($loc) ?>)</label>
                <input type="text" name="<?= $pfx ?>title" value="<?= htmlspecialchars($tr['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Titlu tradus" class="w-full mt-2 p-4 bg-white border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Slug URL (<?= strtoupper($loc) ?>)</label>
                <input type="text" name="<?= $pfx ?>slug" value="<?= htmlspecialchars($tr['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="auto din titlu dacă e gol" class="w-full mt-2 p-4 bg-white border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
            </div>
        </div>
        <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Descriere (<?= strtoupper($loc) ?>)</label>
            <textarea name="<?= $pfx ?>description_long" rows="5" placeholder="Descriere tradusă…" class="w-full mt-2 p-5 bg-white border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 leading-relaxed text-slate-600"><?= htmlspecialchars($tr['description_long'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <?php endforeach; ?>
</div>
