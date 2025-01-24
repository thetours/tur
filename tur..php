<?php
// tur.php - GÜNCELLENMİŞ KOD (TAMAMI - KESİN ÇÖZÜM - SON VERSİYON - GERÇEKTEN!)
// Gerekli dosyaları dahil et
require_once '../config.php';
include '../baglan.php';
include '../auth.php';
include '../sidebar.php';
include '../header.php';

file_put_contents('seo_data_log.txt', print_r($_POST, true));

// Başarı veya hata mesajlarını al
$successMsg = isset($_GET['yes']) ? htmlspecialchars($_GET['yes']) : '';
$errorMsg   = isset($_GET['no']) ? htmlspecialchars($_GET['no']) : '';

// Dil seçenekleri
$languages = ['en' => 'English', 'ru' => 'Rusça', 'de' => 'Almanca', 'pl' => 'Lehçe'];

// Şehir ve bölge verilerini al
$cities      = $pdo->query("SELECT * FROM cities ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_regions = $pdo->query("SELECT id, city_id, name FROM regions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Tür ve Etiket verilerini al
$types = $pdo->query("SELECT * FROM types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$tags  = $pdo->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Booking Form verilerini tutmak için
$existingBooking = [];

// Tur ekleme veya güncelleme işlemi
$isUpdate    = isset($_GET['id']) && is_numeric($_GET['id']);
$tour_id     = $isUpdate ? intval($_GET['id']) : null;
$existing_tour = [];
$translations  = [];

// Seçili etiketleri tutmak için
$selectedTags = [];

// Güncelleme durumunda verileri çek
if ($isUpdate) {
    // Mevcut tur
    $stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
    $stmt->execute([$tour_id]);
    $existing_tour = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_tour) {
        die("Tur bulunamadı.");
    }

    // Çevirileri al
    $stmt = $pdo->prepare("SELECT * FROM tour_translations WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $existing_translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($existing_translations as $trans) {
        $translations[$trans['language_code']] = $trans;
    }

    // Bölge seçimi (JSON -> array)
    $selectedRegions = json_decode($existing_tour['region_id'], true);
    if (!is_array($selectedRegions)) {
        $selectedRegions = [];
    }

    // Seçili etiketler
    if (!empty($existing_tour['tags'])) {
        $selectedTags = json_decode($existing_tour['tags'], true);
        if (!is_array($selectedTags)) {
            $selectedTags = [];
        }
    }

    // ---- Booking Form Verileri ----
    // 1) tour_prices
    $stmt = $pdo->prepare("SELECT * FROM tour_prices WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($prices as $pr) {
        $existingBooking['prices'][$pr['category']] = $pr;
    }

    // 2) tour_dates
    $stmt = $pdo->prepare("SELECT * FROM tour_dates WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $tour_dates = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tour_dates) {
        $existingBooking['dates'] = $tour_dates;
        // Saatleri al
        $tour_times_json = $tour_dates['tour_times'];
        // Json çöz
        $decoded_tour_times = !empty($tour_times_json) ? json_decode($tour_times_json, true) : [];
        $existingBooking['decoded_tour_times'] = $decoded_tour_times;
    }

    // 3) other_tour_prices
    $stmt = $pdo->prepare("SELECT * FROM other_tour_prices WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $other_prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($other_prices as $op) {
        $existingBooking['other_prices'][$op['type']] = $op;
    }

    // 4) group_prices
    $stmt = $pdo->prepare("SELECT * FROM group_prices WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $group_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($group_data) {
        $existingBooking['group_prices'] = $group_data;
    }

    // 5) extras
    $stmt = $pdo->prepare("SELECT * FROM extras WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $extras_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($extras_data) {
        $existingBooking['extras'] = $extras_data;
    }

    // 6) tour_settings
    $stmt = $pdo->prepare("SELECT * FROM tour_settings WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $sett_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sett_data) {
        $existingBooking['settings'] = $sett_data;
    }
} else {
    $selectedRegions = [];
}
// Mevcut dil bilgisini al
$currentLang = isset($_GET['lang']) ? $_GET['lang'] : 'en';

// tour_id tanımlı mı kontrol et
$tour_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Schema seçeneklerini çek
$allSchemas = $pdo->query("SELECT * FROM seo_schemas")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title><?php echo $isUpdate ? 'Tur Güncelleme Sayfası' : 'Tur Ekleme Sayfası'; ?></title>

    <!-- Trumbowyg CSS (MIT Lisanslı, ücretsiz) -->
    <link href="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/ui/trumbowyg.min.css" rel="stylesheet">

    <!-- Diğer CSS kütüphaneleri -->
    <link rel="stylesheet" href="../assets/libs/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="../assets/libs/dropzone/dist/min/dropzone.min.css">
    <link rel="stylesheet" href="../assets/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">

    <style>
        /* Önizleme Stilleri */
        #smallImagePreview img,
        #galleryPreview img {
            max-width: 100px;
            margin: 5px;
            border: 1px solid #ccc;
            padding: 2px;
            display: block;
        }

        .preview-container {
            display: inline-block;
            position: relative;
            /* ÖNEMLİ: .preview-container'a position: relative eklenmeli */
        }

        .preview-container .remove-image {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            cursor: pointer;
            padding: 2px 5px;
            border-radius: 50%;
            font-size: 14px;
            line-height: 1;
            z-index: 10;
            /* ÖNEMLİ: z-index değeri yüksek olmalı */
        }

        .preview-container .remove-image:hover {
            opacity: 0.5;
        }

        .schema-item {
            border: 1px solid #ccc;
            padding: 10px;
            margin: 5px;
        }
    </style>
</head>

<body>
    <div class="body-wrapper">
        <div class="container-fluid">
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success"><?php echo $successMsg; ?></div>
            <?php endif; ?>

            <!-- Form Başlangıcı -->
            <form action="tur_islem.php<?php echo $isUpdate ? '?id=' . $tour_id : ''; ?>"
                method="POST"
                enctype="multipart/form-data"
                class="form-horizontal"
                id="tourForm">

                <!-- Silinecek Galeri Resimleri -->
                <input type="hidden" name="delete_gallery" id="delete_gallery" />
                <input type="hidden" name="seo_data_json" id="seo_data_json" />
                <input type="hidden" name="page_id" value="<?php echo $tour_id; ?>">

                <div class="row">
                    <!-- Sol Taraf -->
                    <div class="col-lg-8">
                        <!-- Dil Seçici Dropdown Menüsü -->
                        <div class="mb-3">
                            <label for="languageSwitcher" class="form-label">Dil Seçiniz</label>
                            <select class="form-select" id="languageSwitcher">
                                <?php foreach ($languages as $lang_code => $lang_name): ?>
                                    <?php
                                    // Dil kodlarını ülke kodlarına eşleştirme
                                    $country_code = '';
                                    switch ($lang_code) {
                                        case 'en':
                                            $country_code = 'us';
                                            break;
                                        case 'ru':
                                            $country_code = 'ru';
                                            break;
                                        case 'de':
                                            $country_code = 'de';
                                            break;
                                        case 'pl':
                                            $country_code = 'pl';
                                            break;
                                            // Diğer diller için eklemeler yapabilirsiniz
                                        default:
                                            $country_code = 'us';
                                    }
                                    ?>
                                    <option value="<?php echo $lang_code; ?>" <?php echo ($currentLang == $lang_code) ? 'selected' : ''; ?> data-country="<?php echo $country_code; ?>">
                                        <?php echo $lang_name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Dil Sekmesi İçerikleri Kapsayıcıya Alındı -->
                        <div class="tab-content" id="languageTabsContent">
                            <?php
                            foreach ($languages as $lc => $ln):
                                $transItem = ($isUpdate && isset($translations[$lc])) ? $translations[$lc] : null;
                            ?>
                                <div class="tab-pane fade <?php echo ($lc == $currentLang ? 'show active' : ''); ?>"
                                    id="<?php echo $lc; ?>"
                                    role="tabpanel"
                                    aria-labelledby="<?php echo $lc; ?>-tab">

                                    <!-- Tur Başlığı -->
                                    <div class="mb-4">
                                        <label class="form-label">Tur Başlığı (<?php echo $ln; ?>)</label>
                                        <input type="text"
                                            class="form-control"
                                            name="title_<?php echo $lc; ?>"
                                            value="<?php echo $transItem ? htmlspecialchars($transItem['title']) : ''; ?>" />
                                    </div>

                                    <!-- Tur Slug -->
                                    <div class="mb-4">
                                        <label class="form-label">Tur Slug (<?php echo $ln; ?>)</label>
                                        <input type="text"
                                            class="form-control"
                                            name="slug_<?php echo $lc; ?>"
                                            value="<?php echo $transItem ? htmlspecialchars($transItem['slug']) : ''; ?>" />
                                        <p class="fs-2">Slug benzersiz olmalıdır (Örn: kral-tur).</p>
                                    </div>

                                    <!-- Tur Açıklaması -->
                                    <div class="mb-4">
                                        <label class="form-label">Tur Açıklaması (<?php echo $ln; ?>)</label>
                                        <textarea class="form-control"
                                            name="description_<?php echo $lc; ?>"
                                            id="description_<?php echo $lc; ?>_trumbowyg"><?php
                                                                                            echo $transItem ? htmlspecialchars($transItem['description']) : '';
                                                                                            ?></textarea>
                                    </div>

                                    <!-- Ek Bilgiler -->
                                    <div class="mb-4">
                                        <label class="form-label">Ek Bilgiler (<?php echo $ln; ?>)</label>
                                        <textarea class="form-control"
                                            name="additional_info_<?php echo $lc; ?>"
                                            id="additional_info_<?php echo $lc; ?>_trumbowyg"><?php
                                                                                                echo $transItem ? htmlspecialchars($transItem['additional_info']) : '';
                                                                                                ?></textarea>
                                    </div>

                                    <!-- Dahil & Hariç Olanlar -->
                                    <div class="mb-4">
                                        <label class="form-label">Dahil ve Hariç Olanlar (<?php echo $ln; ?>)</label>
                                        <div class="repeater">
                                            <div data-repeater-list="includes_excludes_<?php echo $lc; ?>">
                                                <?php
                                                $incExcItems = [];
                                                if ($isUpdate && !empty($existing_tour['includes_excludes'])) {
                                                    $allIncExc = json_decode($existing_tour['includes_excludes'], true);
                                                    $incExcItems = $allIncExc[$lc] ?? [];
                                                }
                                                if (!empty($incExcItems)):
                                                    foreach ($incExcItems as $item):
                                                ?>
                                                        <div data-repeater-item class="mb-3">
                                                            <div class="row">
                                                                <div class="col-md-4">
                                                                    <select class="select2 form-control" name="type">
                                                                        <option value="">Seçiniz</option>
                                                                        <option value="included"
                                                                            <?php echo ($item['type'] == 'included' ? 'selected' : ''); ?>>Dahil</option>
                                                                        <option value="excluded"
                                                                            <?php echo ($item['type'] == 'excluded' ? 'selected' : ''); ?>>Hariç</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 mt-3 mt-md-0">
                                                                    <input type="text"
                                                                        class="form-control"
                                                                        name="item"
                                                                        value="<?php echo htmlspecialchars($item['item']); ?>" />
                                                                </div>
                                                                <div class="col-md-2 mt-3 mt-md-0">
                                                                    <button data-repeater-delete
                                                                        class="btn btn-danger"
                                                                        type="button">
                                                                        <i class="ti ti-x fs-5 d-flex"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    endforeach;
                                                else:
                                                    ?>
                                                    <div data-repeater-item class="mb-3">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <select class="select2 form-control" name="type">
                                                                    <option value="">Seçiniz</option>
                                                                    <option value="included">Dahil</option>
                                                                    <option value="excluded">Hariç</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 mt-3 mt-md-0">
                                                                <input type="text"
                                                                    class="form-control"
                                                                    name="item"
                                                                    placeholder="Örn: Wifi, İçecek" />
                                                            </div>
                                                            <div class="col-md-2 mt-3 mt-md-0">
                                                                <button data-repeater-delete
                                                                    class="btn btn-danger"
                                                                    type="button">
                                                                    <i class="ti ti-x fs-5 d-flex"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" data-repeater-create
                                                class="btn btn-primary mt-2">
                                                + Ekle
                                            </button>
                                        </div>
                                    </div>

                                    <!-- SSS (FAQ) -->
                                    <div class="mb-3">
                                        <label class="form-label">Sıkça Sorulan Sorular (<?php echo $ln; ?>)</label>
                                        <div class="repeater">
                                            <div data-repeater-list="faq_<?php echo $lc; ?>">
                                                <?php
                                                $faqItems = [];
                                                if ($isUpdate && $transItem && isset($transItem['faq'])) {
                                                    $faqItems = json_decode($transItem['faq'], true);
                                                    if (!is_array($faqItems)) {
                                                        $faqItems = [];
                                                    }
                                                }
                                                if (!empty($faqItems)):
                                                    foreach ($faqItems as $f):
                                                ?>
                                                        <div data-repeater-item class="mb-2">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <input type="text"
                                                                        class="form-control"
                                                                        name="question"
                                                                        placeholder="Soru"
                                                                        value="<?php echo htmlspecialchars($f['question']); ?>" />
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <textarea class="form-control"
                                                                        name="answer"
                                                                        placeholder="Cevap"><?php echo htmlspecialchars($f['answer']); ?></textarea>
                                                                </div>
                                                                <div class="col-md-2 mt-3 mt-md-0">
                                                                    <button data-repeater-delete
                                                                        type="button"
                                                                        class="btn btn-danger mt-2">
                                                                        Sil
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    endforeach;
                                                else:
                                                    ?>
                                                    <div data-repeater-item class="mb-3">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <input type="text"
                                                                    class="form-control"
                                                                    name="question"
                                                                    placeholder="Soru" />
                                                            </div>
                                                            <div class="col-md-6">
                                                                <textarea class="form-control"
                                                                    name="answer"
                                                                    placeholder="Cevap"></textarea>
                                                            </div>
                                                            <div class="col-md-2 mt-3 mt-md-0">
                                                                <button data-repeater-delete
                                                                    type="button"
                                                                    class="btn btn-danger mt-2">
                                                                    Sil
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button data-repeater-create
                                                type="button"
                                                class="btn btn-primary mt-2">
                                                + Soru ve Cevap Ekle
                                            </button>
                                        </div>
                                    </div>

                                    <!-- SEO Alanları başlangıç -->
                                    <div class="mb-4 mt-5">
                                        <h4 class="mt-3">SEO Ayarları (<?php echo $ln; ?>)</h4>
                                        <?php
                                        // SEO verilerini veritabanından çekme
                                        $seoMeta = null;
                                        if ($isUpdate) {
                                            $stmt = $pdo->prepare("SELECT * FROM seo_metas WHERE entity_type = 'tour' AND entity_id = ? AND lang = ?");
                                            $stmt->execute([$tour_id, $lc]);
                                            $seoMeta = $stmt->fetch(PDO::FETCH_ASSOC);
                                        }
                                        ?>
                                        <ul class="nav nav-tabs" id="seoTabs-<?php echo $lc; ?>" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="seo-general-tab-<?php echo $lc; ?>" data-bs-toggle="tab" data-bs-target="#seo-general-<?php echo $lc; ?>" type="button" role="tab" aria-controls="seo-general" aria-selected="true">Genel</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="seo-social-tab-<?php echo $lc; ?>" data-bs-toggle="tab" data-bs-target="#seo-social-<?php echo $lc; ?>" type="button" role="tab" aria-controls="seo-social" aria-selected="false">Sosyal Medya</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="seo-advanced-tab-<?php echo $lc; ?>" data-bs-toggle="tab" data-bs-target="#seo-advanced-<?php echo $lc; ?>" type="button" role="tab" aria-controls="seo-advanced" aria-selected="false">Gelişmiş</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="schema-tab-<?php echo $lc ?>" data-bs-toggle="tab" data-bs-target="#schema-<?php echo $lc ?>" type="button" role="tab" aria-controls="schema" aria-selected="false">Schema</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="seo-analysis-tab-<?php echo $lc; ?>" data-bs-toggle="tab" data-bs-target="#seo-analysis-<?php echo $lc; ?>" type="button" role="tab" aria-controls="seo-analysis" aria-selected="false">SEO Analiz</button>
                                            </li>
                                        </ul>

                                        <div class="tab-content" id="settingsTabContent-<?php echo $lc; ?>">
                                            <div class="tab-pane fade show active" id="seo-general-<?php echo $lc; ?>" role="tabpanel" aria-labelledby="seo-general-tab-<?php echo $lc; ?>">
                                                <div class="mb-3">
                                                    <label for="seo_title_<?php echo $lc; ?>" class="form-label">SEO Başlığı (<?php echo $ln; ?>)</label>
                                                    <input type="text" class="form-control" id="seo_title_<?php echo $lc; ?>" name="seo_title[<?php echo $lc; ?>]" value="<?php echo isset($seoMeta['seo_title']) ? htmlspecialchars($seoMeta['seo_title']) : ''; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="seo_description_<?php echo $lc; ?>" class="form-label">SEO Açıklama (<?php echo $ln; ?>)</label>
                                                    <textarea class="form-control" id="seo_description_<?php echo $lc; ?>" name="seo_description[<?php echo $lc; ?>]"><?php echo isset($seoMeta['seo_description']) ? htmlspecialchars($seoMeta['seo_description']) : ''; ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="seo_keywords_<?php echo $lc; ?>" class="form-label">SEO Anahtar Kelimeler (<?php echo $ln; ?>)</label>
                                                    <input type="text" class="form-control" id="seo_keywords_<?php echo $lc; ?>" name="seo_keywords[<?php echo $lc; ?>]" value="<?php echo isset($seoMeta['seo_keywords']) ? htmlspecialchars($seoMeta['seo_keywords']) : ''; ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label> SEO Resmi</label>
                                                    <input type="file"
                                                        name="seo_image[<?php echo $lc; ?>]"
                                                        accept="image/*"
                                                        class="form-control mb-2"
                                                        id="seoImageInput_<?php echo $lc; ?>"
                                                        onchange="previewSeoImage(this)">
                                                    <p class="fs-2 text-center mb-0">
                                                        SEO Resmini ayarlayın (JPG, JPEG, PNG, GIF, WebP, SVG).
                                                    </p>

                                                    <div id="seoImagePreview_<?php echo $lc; ?>" class="mt-2">
                                                        <?php if ($isUpdate && !empty($seoMeta['seo_image'])): ?>
                                                            <div class="preview-container">
                                                                <img src="<?php echo IMAGE_BASE_URL . $seoMeta['seo_image']; ?>" style="max-width: 150px; max-height: 150px;">
                                                                <div class="remove-image" onclick="removeSeoImage('<?php echo $lc; ?>')">x</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="tab-pane fade" id="seo-social-<?php echo $lc; ?>" role="tabpanel" aria-labelledby="seo-social-tab-<?php echo $lc; ?>">
                                                <div class="mb-3">
                                                    <label for="facebook_title_<?php echo $lc; ?>" class="form-label">Facebook Başlığı (<?php echo $ln; ?>)</label>
                                                    <input type="text" class="form-control" id="facebook_title_<?php echo $lc; ?>" name="facebook_title[<?php echo $lc; ?>]" value="<?php echo isset($seoMeta['facebook_title']) ? htmlspecialchars($seoMeta['facebook_title']) : ''; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="facebook_description_<?php echo $lc; ?>" class="form-label">Facebook Açıklaması (<?php echo $ln; ?>)</label>
                                                    <textarea class="form-control" id="facebook_description_<?php echo $lc; ?>" name="facebook_description[<?php echo $lc; ?>]"><?php echo isset($seoMeta['facebook_description']) ? htmlspecialchars($seoMeta['facebook_description']) : ''; ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="facebook_image_<?php echo $lc; ?>" class="form-label">Facebook Resmi (<?php echo $ln; ?>)</label>
                                                    <input type="file" class="form-control" id="facebook_image_<?php echo $lc; ?>" name="facebook_image[<?php echo $lc; ?>]">
                                                    <div id="facebookImagePreview_<?php echo $lc; ?>" class="mt-2">
                                                        <?php if ($isUpdate && !empty($seoMeta['facebook_image'])): ?>
                                                            <div class="preview-container">
                                                                <img src="<?php echo IMAGE_BASE_URL . $seoMeta['facebook_image']; ?>" style="max-width: 150px; max-height: 150px;">
                                                                <div class="remove-image" onclick="removeFacebookImage('<?php echo $lc; ?>')">x</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="tab-pane fade" id="seo-advanced-<?php echo $lc; ?>" role="tabpanel" aria-labelledby="seo-advanced-tab-<?php echo $lc; ?>">
                                                <div class="mb-3">
                                                    <label for="robots_meta_<?php echo $lc; ?>" class="form-label">Robots Meta (<?php echo $ln; ?>)</label>
                                                    <input type="text" class="form-control" id="robots_meta_<?php echo $lc; ?>" name="robots_meta[<?php echo $lc; ?>]" value="<?php echo htmlspecialchars($seoMeta['robots_meta'] ?? ''); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="canonical_url_<?php echo $lc; ?>" class="form-label">Canonical URL (<?php echo $ln; ?>)</label>
                                                    <input type="text" class="form-control" id="canonical_url_<?php echo $lc; ?>" name="canonical_url[<?php echo $lc; ?>]" value="<?php echo htmlspecialchars($seoMeta['canonical_url'] ?? ''); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="breadcrumb_title_<?php echo $lc; ?>" class="form-label">Breadcrumb Title (<?php echo $ln; ?>)</label>
                                                    <input type="text" class="form-control" id="breadcrumb_title_<?php echo $lc; ?>" name="breadcrumb_title[<?php echo $lc; ?>]" value="<?php echo htmlspecialchars($seoMeta['breadcrumb_title'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="schema-<?php echo $lc ?>" role="tabpanel" aria-labelledby="schema-tab-<?php echo $lc; ?>">
                                                <div class="mb-3">
                                                    <label for="schema_data_select_<?php echo $lc; ?>" class="form-label">Schema Verisi (<?php echo $ln; ?>)</label>
                                                    <select class="form-select select2" multiple name="schema_data[<?php echo $lc; ?>][]">
                                                        <?php foreach ($allSchemas as $schema): ?>
                                                            <option value="<?php echo htmlspecialchars($schema['schema_name']) ?>" <?php if ($seoMeta && isset($seoMeta['schema_data']) && in_array($schema['schema_name'], json_decode($seoMeta['schema_data'], true))) {
                                                                                                                                        echo 'selected';
                                                                                                                                    } ?>><?php echo htmlspecialchars($schema['schema_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="seo-analysis-<?php echo $lc; ?>" role="tabpanel" aria-labelledby="seo-analysis-tab-<?php echo $lc; ?>">
                                                <div class="mb-3">
                                                    <label for="seo_analysis_<?php echo $lc; ?>" class="form-label">SEO Analizi (<?php echo $ln; ?>)</label>
                                                    <textarea class="form-control" id="seo_analysis_<?php echo $lc; ?>" name="seo_analysis[<?php echo $lc; ?>]"><?php echo isset($seoMeta['seo_analysis']) ? htmlspecialchars($seoMeta['seo_analysis']) : ''; ?></textarea>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <!-- seo form son -->
                                </div><!-- tab-panel  tab panel son -->
                            <?php endforeach; ?>
                        </div><!-- lc -->

                        <!-- Galeri Bölümü -->
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-7">Galeri</h4>
                                <input type="file"
                                    name="gallery[]"
                                    multiple
                                    accept="image/*"
                                    class="form-control"
                                    id="galleryInput">
                                <p class="fs-2 mt-2">
                                    Galeri resimleri seçin (JPG, JPEG, PNG, GIF, WebP, SVG).
                                </p>
                                <div id="galleryPreview">
                                    <?php
                                    if ($isUpdate && !empty($existing_tour['gallery'])):
                                        $galleryImages = json_decode($existing_tour['gallery'], true);
                                        if (is_array($galleryImages)):
                                            foreach ($galleryImages as $gPath):
                                    ?>
                                                <div class="preview-container">
                                                    <img src="<?php echo IMAGE_BASE_URL . $gPath; ?>" alt="Galeri Resmi">
                                                    <div class="remove-image" data-path="<?php echo htmlspecialchars($gPath); ?>">x</div>
                                                </div>
                                    <?php
                                            endforeach;
                                        endif;
                                    endif; ?>
                                </div>
                            </div>
                        </div>

                    </div><!-- col-lg-8 -->

                    <!-- Sağ Taraf (Sidebar) -->
                    <div class="col-lg-4">
                        <div class="offcanvas-md offcanvas-end overflow-auto"
                            tabindex="-1"
                            id="offcanvasRight"
                            aria-labelledby="offcanvasRightLabel">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title mb-7">Küçük Resim</h4>
                                    <input type="file"
                                        name="small_image"
                                        accept="image/*"
                                        class="form-control mb-2"
                                        id="smallImageInput">
                                    <p class="fs-2 text-center mb-0">
                                        Ürünün küçük resmini ayarlayın (JPG, JPEG, PNG, GIF, WebP, SVG).
                                    </p>
                                    <div id="smallImagePreview">
                                        <?php if ($isUpdate && !empty($existing_tour['small_image'])): ?>
                                            <div class="preview-container">
                                                <img src="<?php echo IMAGE_BASE_URL . $existing_tour['small_image']; ?>" alt="Küçük Resim">
                                                <div class="remove-image"
                                                    data-path="<?php echo htmlspecialchars($existing_tour['small_image']); ?>">x</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-7">
                                        <h4 class="card-title">Durum</h4>
                                        <div class="p-2 h-100 bg-success rounded-circle"></div>
                                    </div>
                                    <select class="form-select mr-sm-2 mb-2"
                                        id="status"
                                        name="status">
                                        <option <?php echo ($isUpdate && $existing_tour['status'] === 'published') ? 'selected' : ''; ?> value="published">Yayınlandı</option>
                                        <option <?php echo ($isUpdate && $existing_tour['status'] === 'draft') ? 'selected' : ''; ?> value="draft">Taslak</option>
                                        <option <?php echo ($isUpdate && $existing_tour['status'] === 'inactive') ? 'selected' : ''; ?> value="inactive">Etkin Değil</option>
                                    </select>
                                    <p class="fs-2 mb-0">
                                        Tur Yayın durumunu ayarlayın.
                                    </p>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title mb-7">Tur Detayı</h4>

                                    <!-- Şehir Yönetimi -->
                                    <div class="mb-3">
                                        <label class="form-label">
                                            Şehir Yönetimi
                                        </label>
                                        <select class="select2 form-control"
                                            name="city_id"
                                            id="city_select">
                                            <option value="">Veri Tabanından Şehirlerden Çek</option>
                                            <?php foreach ($cities as $city): ?>
                                                <option value="<?php echo $city['id']; ?>"
                                                    <?php echo ($isUpdate && $existing_tour['city_id'] == $city['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($city['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="fs-2 mb-0">
                                            Bu Tur Hangi Şehirde olduğu
                                        </p>
                                    </div>

                                    <!-- Bölge Yönetimi -->
                                    <div class="mb-3">
                                        <label class="form-label">Bölge Yönetimi</label>
                                        <select class="select2 form-control"
                                            name="region_id[]"
                                            id="region_select"
                                            multiple="multiple">
                                        </select>
                                        <p class="fs-2 mb-0">
                                            Turun Hangi Bölgeden Olduğu
                                        </p>
                                    </div>

                                    <!-- Tür -->
                                    <div class="mb-3">
                                        <label class="form-label">Tür Yönetimi</label>
                                        <select class="select2 form-control" name="type_id">
                                            <option value="">Veri Tabanından Türlerden Çek</option>
                                            <?php foreach ($types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>"
                                                    <?php echo ($isUpdate && $existing_tour['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="fs-2 mb-0">Turun Türü</p>
                                    </div>

                                    <!-- Etiket -->
                                    <div class="mb-3">
                                        <label class="form-label">Etiket Yönetimi</label>
                                        <select class="select2 form-control"
                                            name="tags[]"
                                            multiple="multiple">
                                            <option value="">Veri Tabanından Etiketlerden Çek</option>
                                            <?php
                                            $selectedTags = $selectedTags ?? [];
                                            foreach ($tags as $tag):
                                                $selected = in_array($tag['id'], $selectedTags) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $tag['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="fs-2 mb-0">Turun Etiketi</p>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div><!-- offcanvas-md -->
                </div><!-- col-lg-4 -->
        </div><!-- row -->

        <div class="container-fluid">
            <!-- ***** Booking Form Entegrasyonu (Tasarım Bozulmadan) ***** -->
            <?php
            // Kayıtlı booking verilerini al
            $bfPrices = $existingBooking['prices']       ?? [];
            $bfDates  = $existingBooking['dates']        ?? [];
            $bfOther  = $existingBooking['other_prices'] ?? [];
            $bfGroup  = $existingBooking['group_prices'] ?? [];
            $bfExtras = $existingBooking['extras']       ?? [];
            $bfSetts  = $existingBooking['settings']     ?? [];
            ?>
            <div class="card mt-5">
                <div class="card-body">
                    <h4 class="mb-3">Booking Form</h4>
                    <!-- Booking Form Tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="bf-price-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#bf-price"
                                type="button"
                                role="tab"
                                aria-controls="bf-price"
                                aria-selected="true">Fiyat</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="bf-date-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#bf-date"
                                type="button"
                                role="tab"
                                aria-controls="bf-date"
                                aria-selected="false">Tarih</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="bf-otherprice-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#bf-otherprice"
                                type="button"
                                role="tab"
                                aria-controls="bf-otherprice"
                                aria-selected="false">Diğer Fiyatlandırma</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="bf-grupprice-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#bf-grupprice"
                                type="button"
                                role="tab"
                                aria-controls="bf-grupprice"
                                aria-selected="false">+1 Grup Fiyatlandırma</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="bf-extras-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#bf-extras"
                                type="button"
                                role="tab"
                                aria-controls="bf-extras"
                                aria-selected="false">Ekstralar</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="bf-otherset-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#bf-otherset"
                                type="button"
                                role="tab"
                                aria-controls="bf-otherset"
                                aria-selected="false">Diğer Ayarlar</button>
                        </li>
                    </ul>

                    <div class="tab-content p-3" id="bfTabContent">
                        <!-- 1) Fiyat Tab -->
                        <div class="tab-pane fade show active"
                            id="bf-price"
                            role="tabpanel"
                            aria-labelledby="bf-price-tab">
                            <!-- adult, child, free -->
                            <?php
                            $catLabels = [
                                'adult' => 'Adult',
                                'child' => 'Child Price',
                                'free'  => 'Free'
                            ];
                            foreach ($catLabels as $catKey => $catLabel):
                                $row = $bfPrices[$catKey] ?? [];
                                $age_range    = htmlspecialchars($row['age_range'] ?? '');
                                $price_euro   = htmlspecialchars($row['price_euro'] ?? '');
                                $price_dollar = htmlspecialchars($row['price_dollar'] ?? '');
                                $price_sterlin = htmlspecialchars($row['price_sterlin'] ?? '');
                                $is_active    = $row['is_active'] ?? '0';
                                $chkActive    = ($is_active == '1' ? 'checked' : '');
                                $chkPassive   = ($is_active == '0' ? 'checked' : '');
                            ?>
                                <div class="row mb-3 align-items-end">
                                    <div class="col-md-2">
                                        <p class="text-start mb-0">
                                            <b><?php echo $catLabel; ?></b>
                                        </p>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Yaş Aralığı</label>
                                        <input type="text"
                                            class="form-control"
                                            name="tour_prices[<?php echo $catKey; ?>][age_range]"
                                            value="<?php echo $age_range; ?>" />
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fiyat Euro</label>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="number"
                                                step="0.01"
                                                class="form-control"
                                                name="tour_prices[<?php echo $catKey; ?>][price_euro]"
                                                value="<?php echo $price_euro; ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fiyat Dolar</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number"
                                                step="0.01"
                                                class="form-control"
                                                name="tour_prices[<?php echo $catKey; ?>][price_dollar]"
                                                value="<?php echo $price_dollar; ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fiyat Sterlin</label>
                                        <div class="input-group">
                                            <span class="input-group-text">£</span>
                                            <input type="number"
                                                step="0.01"
                                                class="form-control"
                                                name="tour_prices[<?php echo $catKey; ?>][price_sterlin]"
                                                value="<?php echo $price_sterlin; ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex">
                                        <div class="form-check me-4">
                                            <input class="form-check-input"
                                                type="radio"
                                                name="tour_prices[<?php echo $catKey; ?>][is_active]"
                                                value="1"
                                                <?php echo $chkActive; ?> />
                                            <label class="form-check-label">Aktif</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                type="radio"
                                                name="tour_prices[<?php echo $catKey; ?>][is_active]"
                                                value="0"
                                                <?php echo $chkPassive; ?> />
                                            <label class="form-check-label">Pasif</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div><!-- #bf-price -->

                        <!-- 2) Tarih Tab -->
                        <?php
                        $days_json   = $bfDates['days']       ?? '[]';
                        $months_json = $bfDates['months']     ?? '[]';
                        $years_json  = $bfDates['years']      ?? '[]';
                        $times_json  = $bfDates['tour_times'] ?? '[]';

                        $daysSelected   = json_decode($days_json, true);
                        $monthsSelected = json_decode($months_json, true);
                        $yearsSelected  = json_decode($years_json, true);
                        $tour_times     = json_decode($times_json, true);
                        ?>
                        <div class="tab-pane fade"
                            id="bf-date"
                            role="tabpanel"
                            aria-labelledby="bf-date-tab">
                            <div class="row mb-4">
                                <label class="form-label">Günler</label>
                                <div class="col text-end mb-2">
                                    <span id="select-days" class="text-primary" style="cursor:pointer;">Select All</span> /
                                    <span id="deselect-days" class="text-danger" style="cursor:pointer;">Deselect All</span>
                                </div>
                                <div class="d-flex flex-wrap">
                                    <?php
                                    $daysMap = [
                                        'monday'    => 'Mon',
                                        'tuesday'   => 'Tue',
                                        'wednesday' => 'Wed',
                                        'thursday'  => 'Thu',
                                        'friday'    => 'Fri',
                                        'saturday'  => 'Sat',
                                        'sunday'    => 'Sun'
                                    ];
                                    foreach ($daysMap as $dk => $dl):
                                        $checked = (is_array($daysSelected) && in_array($dk, $daysSelected)) ? 'checked' : '';
                                    ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input"
                                                type="checkbox"
                                                name="tour_dates[days][]"
                                                value="<?php echo $dk; ?>"
                                                <?php echo $checked; ?> />
                                            <label class="form-check-label"><?php echo $dl; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <label class="form-label">Aylar</label>
                                <div class="col text-end mb-2">
                                    <span id="select-months" class="text-primary" style="cursor:pointer;">Select All</span> /
                                    <span id="deselect-months" class="text-danger" style="cursor:pointer;">Deselect All</span>
                                </div>
                                <div class="d-flex flex-wrap">
                                    <?php
                                    $monthsMap = [
                                        'january'   => 'Jan',
                                        'february'  => 'Feb',
                                        'march'     => 'Mar',
                                        'april'     => 'Apr',
                                        'may'       => 'May',
                                        'june'      => 'Jun',
                                        'july'      => 'Jul',
                                        'august'    => 'Aug',
                                        'september' => 'Sep',
                                        'october'   => 'Oct',
                                        'november'  => 'Nov',
                                        'december'  => 'Dec'
                                    ];
                                    foreach ($monthsMap as $mk => $ml):
                                        $checked = (is_array($monthsSelected) && in_array($mk, $monthsSelected)) ? 'checked' : '';
                                    ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input"
                                                type="checkbox"
                                                name="tour_dates[months][]"
                                                value="<?php echo $mk; ?>"
                                                <?php echo $checked; ?> />
                                            <label class="form-check-label"><?php echo $ml; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <label class="form-label">Yıllar</label>
                                <div class="col text-end mb-2">
                                    <span id="select-years" class="text-primary" style="cursor:pointer;">Select All</span> /
                                    <span id="deselect-years" class="text-danger" style="cursor:pointer;">Deselect All</span>
                                </div>
                                <div class="d-flex flex-wrap">
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear; $y <= $currentYear + 5; $y++):
                                        $checked = (is_array($yearsSelected) && in_array($y, $yearsSelected)) ? 'checked' : '';
                                    ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input class="form-check-input"
                                                type="checkbox"
                                                name="tour_dates[years][]"
                                                value="<?php echo $y; ?>"
                                                <?php echo $checked; ?> />
                                            <label class="form-check-label"><?php echo $y; ?></label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Varsa Tur Saatleri -->
                            <div class="row mb-3">
                                <label class="form-label">Varsa Tur Saatleri</label>

                                <!-- Hidden Input for time values -->
                                <input type="hidden" name="tour_times_json" id="tour_times_json" value="">

                                <div class="time-repeater">
                                    <div data-repeater-list="tour_dates[tour_times]">
                                        <?php
                                        $tour_times_array = $existingBooking['decoded_tour_times'] ?? [];
                                        if (!empty($tour_times_array) && is_array($tour_times_array)):
                                            foreach ($tour_times_array as $time_val): ?>
                                                <div data-repeater-item class="row mb-2">
                                                    <div class="col-md-4">
                                                        <!-- value değerini atadık -->
                                                        <input type="time" class="form-control time-input" value="<?php echo htmlspecialchars($time_val); ?>" />
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button data-repeater-delete class="btn btn-danger" type="button">
                                                            <i class="ti ti-circle-x"></i> Sil
                                                        </button>
                                                    </div>
                                                </div>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                        <!-- Gizli Şablon (her zaman olsun, ama display:none) -->
                                        <div data-repeater-item style="display: none;">
                                            <div class="row mb-2">
                                                <div class="col-md-4">
                                                    <input type="time" class="form-control time-input" />
                                                </div>
                                                <div class="col-md-2">
                                                    <button data-repeater-delete class="btn btn-danger" type="button">
                                                        <i class="ti ti-circle-x"></i> Sil
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" data-repeater-create class="btn btn-primary">
                                        <i class="ti ti-plus"></i> Add Time
                                    </button>
                                </div>
                            </div>

                        </div><!-- .tab-pane -->

                        <!-- 3) Diğer Fiyatlandırma Tab (single, couple, family) -->
                        <div class="tab-pane fade"
                            id="bf-otherprice"
                            role="tabpanel"
                            aria-labelledby="bf-otherprice-tab">
                            <?php
                            $otherCats = ['single' => 'Single', 'couple' => 'Couple', 'family' => 'Family'];
                            foreach ($otherCats as $ocKey => $ocLabel):
                                $rowo = $bfOther[$ocKey] ?? [];
                                $desc = htmlspecialchars($rowo['description'] ?? '');
                                $pe   = htmlspecialchars($rowo['price_euro'] ?? '');
                                $pd   = htmlspecialchars($rowo['price_dollar'] ?? '');
                                $ps   = htmlspecialchars($rowo['price_sterlin'] ?? '');
                                $ia   = $rowo['is_active'] ?? '0';
                                $chActive = ($ia == '1' ? 'checked' : '');
                                $chPassive = ($ia == '0' ? 'checked' : '');
                            ?>
                                <div class="row pt-3 align-items-end">
                                    <div class="col-md-1">
                                        <b><?php echo $ocLabel; ?></b>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Açıklama</label>
                                        <input type="text"
                                            class="form-control"
                                            name="other_tour_prices[<?php echo $ocKey; ?>][description]"
                                            value="<?php echo $desc; ?>" />
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fiyat Euro</label>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="number" step="0.01"
                                                class="form-control"
                                                name="other_tour_prices[<?php echo $ocKey; ?>][price_euro]"
                                                value="<?php echo $pe; ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fiyat Dolar</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01"
                                                class="form-control"
                                                name="other_tour_prices[<?php echo $ocKey; ?>][price_dollar]"
                                                value="<?php echo $pd; ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fiyat Sterlin</label>
                                        <div class="input-group">
                                            <span class="input-group-text">£</span>
                                            <input type="number" step="0.01"
                                                class="form-control"
                                                name="other_tour_prices[<?php echo $ocKey; ?>][price_sterlin]"
                                                value="<?php echo $ps; ?>" />
                                        </div>
                                    </div>
                                    <div class="col-md-3 d-flex">
                                        <div class="form-check me-4">
                                            <input class="form-check-input"
                                                type="radio"
                                                name="other_tour_prices[<?php echo $ocKey; ?>][is_active]"
                                                value="1"
                                                <?php echo $chActive; ?> />
                                            <label class="form-check-label">Aktif</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                type="radio"
                                                name="other_tour_prices[<?php echo $ocKey; ?>][is_active]"
                                                value="0"
                                                <?php echo $chPassive; ?> />
                                            <label class="form-check-label">Pasif</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 4) +1 Grup Fiyat -->
                        <div class="tab-pane fade"
                            id="bf-grupprice"
                            role="tabpanel"
                            aria-labelledby="bf-grupprice-tab">
                            <?php
                            $gmax    = htmlspecialchars($bfGroup['max_people'] ?? '');
                            $geuro   = htmlspecialchars($bfGroup['price_euro'] ?? '');
                            $gdollar = htmlspecialchars($bfGroup['price_dollar'] ?? '');
                            $gster   = htmlspecialchars($bfGroup['price_sterlin'] ?? '');
                            $gp1euro = htmlspecialchars($bfGroup['plus_one_euro'] ?? '');
                            $gp1doll = htmlspecialchars($bfGroup['plus_one_dollar'] ?? '');
                            $gp1ster = htmlspecialchars($bfGroup['plus_one_sterlin'] ?? '');
                            $gAct    = $bfGroup['is_active'] ?? '0';
                            $chActive = ($gAct == '1' ? 'checked' : '');
                            $chPassive = ($gAct == '0' ? 'checked' : '');
                            ?>
                            <div class="row pt-3 align-items-end">
                                <div class="col-md-2">
                                    <b>Grup Fiyat</b>
                                </div>
                                <div class="col-md-2">
                                    <label>Grup Max Kişi Sayısı</label>
                                    <input type="number"
                                        class="form-control"
                                        name="group_prices[max_people]"
                                        value="<?php echo $gmax; ?>" />
                                </div>
                                <div class="col-md-2">
                                    <label>Grup Euro</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="number" step="0.01"
                                            class="form-control"
                                            name="group_prices[price_euro]"
                                            value="<?php echo $geuro; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label>Grup Dolar</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01"
                                            class="form-control"
                                            name="group_prices[price_dollar]"
                                            value="<?php echo $gdollar; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label>Grup Sterlin</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01"
                                            class="form-control"
                                            name="group_prices[price_sterlin]"
                                            value="<?php echo $gster; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label>+1 Kişi Fiyatı Euro</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="number" step="0.01"
                                            class="form-control"
                                            name="group_prices[plus_one_euro]"
                                            value="<?php echo $gp1euro; ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="row pt-3 align-items-end">
                                <div class="col-md-2">
                                    <label>+1 Kişi Fiyatı Dolar</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01"
                                            class="form-control"
                                            name="group_prices[plus_one_dollar]"
                                            value="<?php echo $gp1doll; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label>+1 Kişi Fiyatı Sterlin</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01"
                                            class="form-control"
                                            name="group_prices[plus_one_sterlin]"
                                            value="<?php echo $gp1ster; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex">
                                    <div class="form-check me-4">
                                        <input class="form-check-input"
                                            type="radio"
                                            name="group_prices[is_active]"
                                            value="1"
                                            <?php echo $chActive; ?> />
                                        <label class="form-check-label">Aktif</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input"
                                            type="radio"
                                            name="group_prices[is_active]"
                                            value="0"
                                            <?php echo $chPassive; ?> />
                                        <label class="form-check-label">Pasif</label>
                                    </div>
                                </div>
                            </div>
                        </div><!-- #bf-grupprice -->

                        <!-- 5) Ekstralar Tab -->
                        <div class="tab-pane fade"
                            id="bf-extras"
                            role="tabpanel"
                            aria-labelledby="bf-extras-tab">

                            <!-- Hidden Input for extras values -->
                            <input type="hidden" name="extras_json" id="extras_json" value="">
                            <div class="extras-repeater">
                                <div data-repeater-list="extras">
                                    <?php
                                    if (!empty($bfExtras)):
                                        foreach ($bfExtras as $index => $ex):
                                            $sName = htmlspecialchars($ex['service_name'] ?? '');
                                            $pe    = htmlspecialchars($ex['price_euro'] ?? '');
                                            $pd    = htmlspecialchars($ex['price_dollar'] ?? '');
                                            $ps    = htmlspecialchars($ex['price_sterlin'] ?? '');
                                            $is_active = $ex['is_active'] ?? '0';
                                            $chActive = ($is_active == '1') ? 'checked' : '';
                                            $chPassive = ($is_active == '0') ? 'checked' : '';
                                    ?>
                                            <div data-repeater-item class="row mb-3">
                                                <div class="col-md-2">
                                                    <label>Hizmet İsmi</label>
                                                    <!-- Doğru `name` attribute'u-->
                                                    <input type="text" name="service_name" class="form-control" value="<?php echo $sName; ?>" />
                                                </div>
                                                <div class="col-md-2">
                                                    <label>Fiyat Euro</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">€</span>
                                                        <!-- Doğru `name` attribute'u-->
                                                        <input type="number" step="0.01" name="price_euro" class="form-control" value="<?php echo $pe; ?>" />
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label>Fiyat Dolar</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <!-- Doğru `name` attribute'u-->
                                                        <input type="number" step="0.01" name="price_dollar" class="form-control" value="<?php echo $pd; ?>" />
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label>Fiyat Sterlin</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">£</span>
                                                        <!-- Doğru `name` attribute'u-->
                                                        <input type="number" step="0.01" name="price_sterlin" class="form-control" value="<?php echo $ps; ?>" />
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label>Durum</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="is_active" value="1" <?php echo $chActive; ?> />
                                                        <label class="form-check-label">Aktif</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="is_active" value="0" <?php echo $chPassive; ?> />
                                                        <label class="form-check-label">Pasif</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 mt-4">
                                                    <button data-repeater-delete type="button" class="btn btn-danger">
                                                        <i class="ti ti-circle-x"></i> Sil
                                                    </button>
                                                </div>
                                            </div>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                    <!-- Gizli Şablon -->
                                    <div data-repeater-item style="display: none;">
                                        <div class="row mb-3">
                                            <div class="col-md-2">
                                                <label>Hizmet İsmi</label>
                                                <!-- Doğru `name` attribute'u-->
                                                <input type="text" name="service_name" class="form-control" />
                                            </div>
                                            <div class="col-md-2">
                                                <label>Fiyat Euro</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">€</span>
                                                    <!-- Doğru `name` attribute'u-->
                                                    <input type="number" step="0.01" name="price_euro" class="form-control" />
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <label>Fiyat Dolar</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <!-- Doğru `name` attribute'u-->
                                                    <input type="number" step="0.01" name="price_dollar" class="form-control" />
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <label>Fiyat Sterlin</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">£</span>
                                                    <!-- Doğru `name` attribute'u-->
                                                    <input type="number" step="0.01" name="price_sterlin" class="form-control" />
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <label>Durum</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="is_active" value="1" checked />
                                                    <label class="form-check-label">Aktif</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="is_active" value="0" />
                                                    <label class="form-check-label">Pasif</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2 mt-4">
                                                <button data-repeater-delete type="button" class="btn btn-danger">
                                                    <i class="ti ti-circle-x"></i> Sil
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" data-repeater-create class="btn btn-primary">
                                    <i class="ti ti-plus"></i> Hizmet Ekle
                                </button>
                            </div>
                        </div><!-- #bf-extras -->

                        <!-- 6) Diğer Ayarlar Tab -->
                        <div class="tab-pane fade"
                            id="bf-otherset"
                            role="tabpanel"
                            aria-labelledby="bf-otherset-tab">
                            <?php
                            $help_title     = htmlspecialchars($bfSetts['help_title'] ?? '');
                            $help_link      = htmlspecialchars($bfSetts['help_link'] ?? '');
                            $hotel_transfer = $bfSetts['hotel_transfer'] ?? 1;
                            $trYes          = ($hotel_transfer == 1 ? 'checked' : '');
                            $trNo           = ($hotel_transfer == 0 ? 'checked' : '');
                            ?>
                            <div class="row pt-3 align-items-end">
                                <div class="col-md-2">
                                    <b>Diğer Ayarlar</b>
                                </div>
                                <div class="col-md-3">
                                    <label>Yardım Başlığı</label>
                                    <input type="text"
                                        name="tour_settings[help_title]"
                                        class="form-control"
                                        value="<?php echo $help_title; ?>" />
                                </div>
                                <div class="col-md-3">
                                    <label>Yardım Linki</label>
                                    <input type="url"
                                        name="tour_settings[help_link]"
                                        class="form-control"
                                        value="<?php echo $help_link; ?>" />
                                </div>
                                <div class="col-md-4">
                                    <label>Hotel Transfer</label>
                                    <div class="form-check form-check-inline ms-3">
                                        <input class="form-check-input"
                                            type="radio"
                                            name="tour_settings[hotel_transfer]"
                                            id="hotel_transfer_yes"
                                            value="1"
                                            <?php echo $trYes; ?> />
                                        <label class="form-check-label" for="hotel_transfer_yes">Yes</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input"
                                            type="radio"
                                            name="tour_settings[hotel_transfer]"
                                            id="hotel_transfer_no"
                                            value="0"
                                            <?php echo $trNo; ?> />
                                        <label class="form-check-label" for="hotel_transfer_no">No</label>
                                    </div>
                                </div>
                            </div>
                        </div><!-- #bf-otherset -->

                    </div>
                </div>
                <!-- ***** Booking Form Sonu ***** -->

            </div><!-- container-fluid -->

            <!-- Form Butonları -->
            <div class="mt-7">
                <button type="submit" class="btn btn-primary">
                    <?php echo $isUpdate ? 'Güncelle' : 'Kaydet'; ?>
                </button>
                <button type="button"
                    class="btn btn-outline-danger ms-3"
                    onclick="window.location.href='tours.php'">
                    İptal Et
                </button>
            </div>

            <!-- Galeri Resimleri Silme Alanı -->
            <input type="hidden" name="delete_gallery" id="delete_gallery" value="">

            <!-- SEO Resmi Silme Alanı -->
            <input type="hidden" name="delete_seo_image" id="delete_seo_image" value="">

            <!-- Facebook Resmi Silme Alanı -->
            <input type="hidden" name="delete_facebook_image" id="delete_facebook_image" value="">

            <!-- Küçük Resim Silme Alanı -->
            <input type="hidden" name="delete_small_image" id="delete_small_image" value="0">

            <!-- Tur ID'si -->
            <input type="hidden" name="tour_id" value="<?php echo htmlspecialchars($tour_id); ?>">


            </form><!-- Form Sonu -->

        </div><!-- container-fluid -->
    </div><!-- body-wrapper -->
    <br>

    <!-- 1) jQuery (Trumbowyg bağımlı) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- 2) Bootstrap ve diğer scriptler -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/vendor.min.js"></script>
    <script src="../assets/libs/simplebar/dist/simplebar.min.js"></script>
    <script src="../assets/js/theme/app.init.js"></script>
    <script src="../assets/js/theme/theme.js"></script>
    <script src="../assets/js/theme/app.min.js"></script>
    <script src="../assets/js/theme/sidebarmenu.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script src="../assets/libs/quill/dist/quill.min.js"></script>
    <script src="../assets/js/forms/quill-init.js"></script>
    <script src="../assets/libs/dropzone/dist/min/dropzone.min.js"></script>
    <script src="../assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="../assets/libs/select2/dist/js/select2.min.js"></script>
    <script src="../assets/libs/jquery.repeater/jquery.repeater.min.js"></script>
    <script src="../assets/libs/jquery-validation/dist/jquery.validate.min.js"></script>
    <script src="../assets/js/forms/repeater-init.js"></script>
    <script src="../assets/libs/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>

    <!-- 3) Trumbowyg JS -->
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/trumbowyg.min.js"></script>
    <!-- Trumbowyg Plugins (isteğe bağlı) -->
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/plugins/table/trumbowyg.table.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/plugins/base64/trumbowyg.base64.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/plugins/colors/trumbowyg.colors.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/plugins/noembed/trumbowyg.noembed.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.25.1/dist/plugins/pasteembed/trumbowyg.pasteembed.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select2 Başlatma
            $('.select2').select2({
                width: '100%'
            });

            // Dinamik Bölge Yönetimi
            var allRegions = <?php echo json_encode($all_regions); ?>;
            var selectedRegions = <?php echo $isUpdate ? json_encode($selectedRegions) : '[]'; ?>;

            function filterRegionsByCity(cityId) {
                var regionSelect = document.getElementById('region_select');
                regionSelect.innerHTML = '';

                if (!cityId) {
                    var noOpt = document.createElement('option');
                    noOpt.text = "Veri Tabanından Bölge Çek";
                    noOpt.disabled = true;
                    regionSelect.appendChild(noOpt);
                    $(regionSelect).trigger('change');
                    return;
                }

                var filtered = allRegions.filter(function(reg) {
                    return parseInt(reg.city_id) === parseInt(cityId);
                });

                if (filtered.length > 0) {
                    filtered.forEach(function(r) {
                        var opt = document.createElement('option');
                        opt.value = r.id;
                        opt.text = r.name;
                        if (selectedRegions.includes(parseInt(r.id))) {
                            opt.selected = true;
                        }
                        regionSelect.appendChild(opt);
                    });
                } else {
                    var opt2 = document.createElement('option');
                    opt2.text = "Bu şehir için sonuç bulunamadı";
                    opt2.disabled = true;
                    regionSelect.appendChild(opt2);
                }
                $(regionSelect).trigger('change');
            }

            // Şehir değişince
            $('#city_select').on('change', function() {
                filterRegionsByCity($(this).val());
            });
            // İlk açılış
            filterRegionsByCity($('#city_select').val());

            // Repeater (initEmpty: false)
            $('.repeater').repeater({
                initEmpty: false,
                defaultValues: {},
                show: function() {
                    $(this).slideDown();
                    $(this).find('.select2').select2({
                        width: '100%'
                    });
                },
                hide: function(deleteElement) {
                    if (confirm('Bu alanı silmek istediğinize emin misiniz?')) {
                        $(this).slideUp(deleteElement);
                    }
                }
            });

            // time & extras repeater
            $('.time-repeater, .extras-repeater').repeater({
                initEmpty: false,
                defaultValues: {},
                show: function() {
                    $(this).slideDown();
                },
                hide: function(deleteElement) {
                    if (confirm('Bu alanı silmek istediğinize emin misiniz?')) {
                        $(this).slideUp(deleteElement);
                    }
                }
            });

            // Trumbowyg Başlatma
            var textareas = document.querySelectorAll('textarea[id$="_trumbowyg"]');
            textareas.forEach(function(ta) {
                $('#' + ta.id).trumbowyg({
                    btns: [
                        ['viewHTML'],
                        ['undo', 'redo'],
                        ['formatting'],
                        ['strong', 'em', 'del'],
                        ['superscript', 'subscript'],
                        ['link'],
                        ['insertImage'],
                        ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
                        ['unorderedList', 'orderedList'],
                        ['horizontalRule'],
                        ['removeformat'],
                        ['fullscreen']
                    ]
                });
            });

            // Galeri İşlemleri
            const galleryInput = document.getElementById('galleryInput');
            const galleryPreview = document.getElementById('galleryPreview');
            const deleteGallery = document.getElementById('delete_gallery');
            const dataTransfer = new DataTransfer();

            galleryPreview.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-image')) {
                    const delPath = event.target.getAttribute('data-path');
                    if (delPath) {
                        let oldVal = deleteGallery.value;
                        if (oldVal) oldVal += ',' + delPath;
                        else oldVal = delPath;
                        deleteGallery.value = oldVal;
                        event.target.parentElement.remove();
                    } else {
                        // Yeni eklenen resim
                        const fname = event.target.getAttribute('data-name');
                        for (let i = 0; i < dataTransfer.files.length; i++) {
                            if (dataTransfer.files[i].name === fname) {
                                dataTransfer.items.remove(i);
                                break;
                            }
                        }
                        galleryInput.files = dataTransfer.files;
                        event.target.parentElement.remove();
                    }
                }
            });

            galleryInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                files.forEach(function(f) {
                    dataTransfer.items.add(f);
                    let fr = new FileReader();
                    fr.onload = function(ev) {
                        let c = document.createElement('div');
                        c.className = 'preview-container';
                        let i = document.createElement('img');
                        i.src = ev.target.result;
                        i.alt = 'Galeri Resmi';
                        let rm = document.createElement('div');
                        rm.className = 'remove-image';
                        rm.textContent = 'x';
                        rm.setAttribute('data-name', f.name);
                        c.appendChild(i);
                        c.appendChild(rm);
                        galleryPreview.appendChild(c);
                    };
                    fr.readAsDataURL(f);
                });
                galleryInput.files = dataTransfer.files;
            });

            // Küçük Resim İşlemleri
            const smallImageInput = document.getElementById('smallImageInput');
            const smallImagePreview = document.getElementById('smallImagePreview');

            if (smallImagePreview.querySelector('img')) {
                let rmBtn = smallImagePreview.querySelector('.remove-image');
                rmBtn.addEventListener('click', function() {
                    smallImageInput.value = '';
                    smallImagePreview.innerHTML = '';

                    // Küçük resim silme
                    $('#delete_small_image').val('1'); // Formunuza eklemeniz gereken gizli alan
                });
            }

            smallImageInput.addEventListener('change', function() {
                smallImagePreview.innerHTML = '';
                if (this.files && this.files[0]) {
                    let file = this.files[0];
                    let allowExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                    let ext = file.name.split('.').pop().toLowerCase();
                    if (!allowExt.includes(ext)) {
                        alert("Geçersiz dosya türü. Sadece JPG, JPEG, PNG, GIF, WebP, SVG.");
                        this.value = '';
                        return;
                    }
                    let fr = new FileReader();
                    fr.onload = function(ev) {
                        let c = document.createElement('div');
                        c.className = 'preview-container';
                        let i = document.createElement('img');
                        i.src = ev.target.result;
                        i.alt = 'Küçük Resim';
                        let r = document.createElement('div');
                        r.className = 'remove-image';
                        r.textContent = 'x';
                        r.setAttribute('data-type', 'small_image');
                        r.onclick = function() {
                            removeSmallImage();
                        };
                        c.appendChild(i);
                        c.appendChild(r);
                        smallImagePreview.appendChild(c);
                    };
                    fr.readAsDataURL(file);
                }
            });

            // Select All / Deselect All İşlevleri
            document.getElementById('select-days').addEventListener('click', function() {
                document.querySelectorAll('input[name="tour_dates[days][]"]').forEach(function(cb) {
                    cb.checked = true;
                });
            });
            document.getElementById('deselect-days').addEventListener('click', function() {
                document.querySelectorAll('input[name="tour_dates[days][]"]').forEach(function(cb) {
                    cb.checked = false;
                });
            });
            document.getElementById('select-months').addEventListener('click', function() {
                document.querySelectorAll('input[name="tour_dates[months][]"]').forEach(function(cb) {
                    cb.checked = true;
                });
            });
            document.getElementById('deselect-months').addEventListener('click', function() {
                document.querySelectorAll('input[name="tour_dates[months][]"]').forEach(function(cb) {
                    cb.checked = false;
                });
            });
            document.getElementById('select-years').addEventListener('click', function() {
                document.querySelectorAll('input[name="tour_dates[years][]"]').forEach(function(cb) {
                    cb.checked = true;
                });
            });
            document.getElementById('deselect-years').addEventListener('click', function() {
                document.querySelectorAll('input[name="tour_dates[years][]"]').forEach(function(cb) {
                    cb.checked = false;
                });
            });

            // Dil Değişikliğinde İçeriği ve SEO Formunu Güncelle
            function handleLanguageChange() {
                var selectedLang = $('#languageSwitcher').val();

                // İçerik Tablarını Güncelle
                $('.tab-pane').removeClass('show active');
                $('#' + selectedLang).addClass('show active');

                // SEO Tablarını Güncelle
                $('.tab-content[id^="settingsTabContent-"]').hide();
                $('#settingsTabContent-' + selectedLang).show();
                $('#seoTabs-' + selectedLang + ' .nav-link:first').tab('show');

                // Fiyat tabını aktif et
                $('#bf-price-tab').tab('show');
                $('#bf-price').addClass('show active');
            }

            $('#languageSwitcher').on('change', handleLanguageChange);

            // Sayfa Yüklendiğinde İlk Dil İçeriğini ve SEO Formunu Göster
            var initialLang = $('#languageSwitcher').val();
            $('.tab-pane').removeClass('show active');
            $('#' + initialLang).addClass('show active');
            $('.tab-content[id^="settingsTabContent-"]').hide();
            $('#settingsTabContent-' + initialLang).show();
            $('#seoTabs-' + initialLang + ' .nav-link:first').tab('show');

            // Tablar arasında geçiş yapılırken içeriğin doğru şekilde görünmesini sağlıyoruz
            $('#bfTabContent').on('click', '.nav-link', function() {
                var targetTab = $(this).attr('data-bs-target');
                $(targetTab).addClass('show active').siblings('.tab-pane').removeClass('show active');
            });

            // Form Gönderimi Öncesi İşlemler
            $('#tourForm').on('submit', function(e) {
                // Öncelikle, formun doğru şekilde çalışması için gerekli tüm işlemleri yapın
                let seoData = {
                    seo_metas: {}
                };

                $('#languageTabsContent .tab-pane').each(function() {
                    var langCode = $(this).attr('id');
                    seoData.seo_metas[langCode] = {};

                    // Genel Sekmesi
                    seoData.seo_metas[langCode].general = {
                        seo_title: $('#seo-general-' + langCode + ' input[name="seo_title[' + langCode + ']"]').val() || '',
                        seo_description: $('#seo-general-' + langCode + ' textarea[name="seo_description[' + langCode + ']"]').val() || '',
                        seo_keywords: $('#seo-general-' + langCode + ' input[name="seo_keywords[' + langCode + ']"]').val() || '',
                        seo_image: $('#seo-general-' + langCode + ' input[name="seo_image[' + langCode + ']"]').val() ? $('#seo-general-' + langCode + ' input[name="seo_image[' + langCode + ']"]').val().split('\\').pop() : ''
                    };

                    // Sosyal Medya Sekmesi
                    seoData.seo_metas[langCode].social = {
                        facebook_title: $('#seo-social-' + langCode + ' input[name="facebook_title[' + langCode + ']"]').val() || '',
                        facebook_description: $('#seo-social-' + langCode + ' textarea[name="facebook_description[' + langCode + ']"]').val() || '',
                        facebook_image: $('#seo-social-' + langCode + ' input[name="facebook_image[' + langCode + ']"]').val() ? $('#seo-social-' + langCode + ' input[name="facebook_image[' + langCode + ']"]').val().split('\\').pop() : ''
                    };

                    // Gelişmiş Sekmesi
                    seoData.seo_metas[langCode].advanced = {
                        robots_meta: $('#seo-advanced-' + langCode + ' input[name="robots_meta[' + langCode + ']"]').val() || '',
                        canonical_url: $('#seo-advanced-' + langCode + ' input[name="canonical_url[' + langCode + ']"]').val() || '',
                        breadcrumb_title: $('#seo-advanced-' + langCode + ' input[name="breadcrumb_title[' + langCode + ']"]').val() || ''
                    };

                    // Schema Sekmesi
                    seoData.seo_metas[langCode].schema = {
                        schema_data: $('#schema-' + langCode + ' select[name="schema_data[' + langCode + '][]"]').val() || []
                    };

                    // SEO Analiz Sekmesi
                    seoData.seo_metas[langCode].analysis = {
                        seo_analysis: $('#seo-analysis-' + langCode + ' textarea[name="seo_analysis[' + langCode + ']"]').val() || ''
                    };
                });
                $('#seo_data_json').val(JSON.stringify(seoData));

                // time-input değerlerini al
                let timeValues = [];
                $('.time-repeater .time-input').each(function() {
                    let timeValue = $(this).val();
                    if (timeValue) {
                        timeValues.push(timeValue);
                    }
                });
                // JSON string'e çevir
                const timeJson = JSON.stringify(timeValues);
                // Hidden input'a yaz
                $('#tour_times_json').val(timeJson);

                // Ekstraları JSON'a dönüştürme
                let extrasData = $('.extras-repeater [data-repeater-item]').map(function() {
                    let itemData = {};
                    $(this).find('input, select').each(function() {
                        itemData[this.name] = $(this).val();
                    });
                    return itemData;
                }).get();

                // extrasData'yı temizleyip name attribute'ları düzelt
                let cleanedExtrasData = extrasData.map(item => {
                    let cleanedItem = {};
                    for (const key in item) {
                        if (item.hasOwnProperty(key)) {
                            const match = key.match(/extras\[\d+\]\[(.*)\]/);
                            if (match) {
                                cleanedItem[match[1]] = item[key];
                            }
                        }
                    }
                    return cleanedItem;
                });
                const extrasJson = JSON.stringify(cleanedExtrasData);
                $('#extras_json').val(extrasJson);

                // Diğer fiyatları JSON'a dönüştürme
                let otherPricesData = {
                    'single': {
                        'description': $('input[name="other_tour_prices[single][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[single][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[single][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[single][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[single][is_active]"]:checked').val() || 0
                    },
                    'couple': {
                        'description': $('input[name="other_tour_prices[couple][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[couple][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[couple][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[couple][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[couple][is_active]"]:checked').val() || 0
                    },
                    'family': {
                        'description': $('input[name="other_tour_prices[family][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[family][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[family][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[family][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[family][is_active]"]:checked').val() || 0
                    }
                };

                const otherPricesJson = JSON.stringify(otherPricesData);
                $('#other_prices_json').val(otherPricesJson);

                // Boş ekstraları kaldırma
                $('.extras-repeater [data-repeater-item]').each(function(index) {
                    const serviceInput = $(this).find('input[name="service_name"]');
                    const serviceVal = serviceInput.val() || '';
                    if (!serviceVal.trim()) {
                        $(this).remove();
                    } else {
                        // Doğru name'i ekledik
                        serviceInput.attr('name', 'extras[' + index + '][service_name]');
                        $(this).find('input[name="price_euro"]').attr('name', 'extras[' + index + '][price_euro]');
                        $(this).find('input[name="price_dollar"]').attr('name', 'extras[' + index + '][price_dollar]');
                        $(this).find('input[name="price_sterlin"]').attr('name', 'extras[' + index + '][price_sterlin]');
                        $(this).find('input[name="is_active"]').attr('name', 'extras[' + index + '][is_active]');
                    }
                });
            });

            // removeSeoImage ve removeFacebookImage fonksiyonlarını global hale getir
            window.removeSeoImage = function(langCode) {
                var seoInput = $('#seoImageInput_' + langCode);
                var currentSeoImage = seoInput.data('current-image');
                var previewContainer = $('#seoImagePreview_' + langCode);
                if (currentSeoImage) {
                    var deleteSeoImage = $('#delete_seo_image').val();
                    if (deleteSeoImage) {
                        deleteSeoImage += ',' + langCode + ':' + currentSeoImage;
                    } else {
                        deleteSeoImage = langCode + ':' + currentSeoImage;
                    }
                    $('#delete_seo_image').val(deleteSeoImage);
                }
                seoInput.val('');
                previewContainer.html('');
            }

            window.removeFacebookImage = function(langCode) {
                var fbInput = $('#facebook_image_' + langCode);
                var currentFbImage = fbInput.data('current-image');
                var previewContainer = $('#facebookImagePreview_' + langCode);

                if (currentFbImage) {
                    var deleteFacebookImage = $('#delete_facebook_image').val();
                    if (deleteFacebookImage) {
                        deleteFacebookImage += ',' + langCode + ':' + currentFbImage;
                    } else {
                        deleteFacebookImage = langCode + ':' + currentFbImage;
                    }
                    $('#delete_facebook_image').val(deleteFacebookImage);
                }

                fbInput.val('');
                previewContainer.html('');
            }


            // Küçük Resim Silme Fonksiyonu
            window.removeSmallImage = function() {
                $('#smallImageInput').val('');
                $('#smallImagePreview').html('');
                $('#delete_small_image').val('1'); // Sunucuya küçük resmin silinmesi gerektiğini bildir
            }

            //  Facebook Resmi Önizlemesi için Fonksiyon
            function previewFacebookImage(input) {
                if (input.files && input.files[0]) {
                    var langCode = input.id.split('_').pop(); // Örneğin 'facebook_image_en' -> 'en'
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#facebookImagePreview_' + langCode).html(`
                        <div class="preview-container">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px;">
                            <div class="remove-image" onclick="removeFacebookImage('${langCode}')">x</div>
                        </div>
                    `);
                        // Yeni yüklenen resim için data-current-image'i güncelleyin
                        $('#facebook_image_' + langCode).data('current-image', '');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // SEO Resmi Önizlemesi için Fonksiyon
            function previewSeoImage(input) {
                if (input.files && input.files[0]) {
                    var langCode = input.id.split('_').pop(); // Örneğin 'seoImageInput_en' -> 'en'
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#seoImagePreview_' + langCode).html(`
                        <div class="preview-container">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px;">
                            <div class="remove-image" onclick="removeSeoImage('${langCode}')">x</div>
                        </div>
                    `);
                        // Yeni yüklenen resim için data-current-image'i güncelleyin
                        $('#seoImageInput_' + langCode).data('current-image', '');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // Fiyat tabını varsayılan olarak göster
            $('#bf-price-tab').tab('show');

            // Sayfa yüklendiğinde 'Fiyat' tabını ve içeriklerini düzgün şekilde aç
            $(window).on('load', function() {
                $('#bf-price-tab').tab('show'); // Sayfa yüklendiğinde 'Fiyat' tabını aktif et
                $('#bf-price').addClass('show active'); // 'Fiyat' tabının içeriğini göster
            });

            // Tablar arasında geçiş yapılırken içeriğin doğru şekilde görünmesini sağlıyoruz
            $('#bfTabContent').on('click', '.nav-link', function() {
                var targetTab = $(this).attr('data-bs-target');
                $(targetTab).addClass('show active').siblings('.tab-pane').removeClass('show active');
            });

            // Sayfa yüklendiğinde SEO Ayarları - Başlangıçta "Genel" tabını aktif et
            $(window).on('load', function() {
                $('#seoTabs-<?php echo $lc; ?> #seo-general-tab-<?php echo $lc; ?>').tab('show');
                $('#seo-general-<?php echo $lc; ?>').addClass('show active');
            });

            // Dil Değişikliğinde İçeriği ve SEO Formunu Güncelle
            function handleLanguageChange() {
                var selectedLang = $('#languageSwitcher').val();

                // İçerik Tablarını Güncelle
                $('.tab-pane').removeClass('show active');
                $('#' + selectedLang).addClass('show active');

                // SEO Tablarını Güncelle
                $('.tab-content[id^="settingsTabContent-"]').hide();
                $('#settingsTabContent-' + selectedLang).show();
                $('#seoTabs-' + selectedLang + ' .nav-link:first').tab('show');

                // Fiyat tabını aktif et
                $('#bf-price-tab').tab('show');
                $('#bf-price').addClass('show active');
            }

            $('#languageSwitcher').on('change', handleLanguageChange);

            // Sayfa Yüklendiğinde İlk Dil İçeriğini ve SEO Formunu Göster
            var initialLang = $('#languageSwitcher').val();
            $('.tab-pane').removeClass('show active');
            $('#' + initialLang).addClass('show active');
            $('.tab-content[id^="settingsTabContent-"]').hide();
            $('#settingsTabContent-' + initialLang).show();
            $('#seoTabs-' + initialLang + ' .nav-link:first').tab('show');

            // Sayfa yüklendiğinde SEO Ayarları - Başlangıçta "Genel" tabını aktif et
            $('#seoTabs-' + initialLang + ' #seo-general-tab-' + initialLang).tab('show');
            $('#seo-general-' + initialLang).addClass('show active');

            // Fiyat tabını varsayılan olarak göster
            $('#bf-price-tab').tab('show');

            // Form Gönderimi Öncesi İşlemler
            $('#tourForm').on('submit', function(e) {
                // Öncelikle, formun doğru şekilde çalışması için gerekli tüm işlemleri yapın
                let seoData = {
                    seo_metas: {}
                };

                $('#languageTabsContent .tab-pane').each(function() {
                    var langCode = $(this).attr('id');
                    seoData.seo_metas[langCode] = {};

                    // Genel Sekmesi
                    seoData.seo_metas[langCode].general = {
                        seo_title: $('#seo-general-' + langCode + ' input[name="seo_title[' + langCode + ']"]').val() || '',
                        seo_description: $('#seo-general-' + langCode + ' textarea[name="seo_description[' + langCode + ']"]').val() || '',
                        seo_keywords: $('#seo-general-' + langCode + ' input[name="seo_keywords[' + langCode + ']"]').val() || '',
                        seo_image: $('#seo-general-' + langCode + ' input[name="seo_image[' + langCode + ']"]').val() ? $('#seo-general-' + langCode + ' input[name="seo_image[' + langCode + ']"]').val().split('\\').pop() : ''
                    };

                    // Sosyal Medya Sekmesi
                    seoData.seo_metas[langCode].social = {
                        facebook_title: $('#seo-social-' + langCode + ' input[name="facebook_title[' + langCode + ']"]').val() || '',
                        facebook_description: $('#seo-social-' + langCode + ' textarea[name="facebook_description[' + langCode + ']"]').val() || '',
                        facebook_image: $('#seo-social-' + langCode + ' input[name="facebook_image[' + langCode + ']"]').val() ? $('#seo-social-' + langCode + ' input[name="facebook_image[' + langCode + ']"]').val().split('\\').pop() : ''
                    };

                    // Gelişmiş Sekmesi
                    seoData.seo_metas[langCode].advanced = {
                        robots_meta: $('#seo-advanced-' + langCode + ' input[name="robots_meta[' + langCode + ']"]').val() || '',
                        canonical_url: $('#seo-advanced-' + langCode + ' input[name="canonical_url[' + langCode + ']"]').val() || '',
                        breadcrumb_title: $('#seo-advanced-' + langCode + ' input[name="breadcrumb_title[' + langCode + ']"]').val() || ''
                    };

                    // Schema Sekmesi
                    seoData.seo_metas[langCode].schema = {
                        schema_data: $('#schema-' + langCode + ' select[name="schema_data[' + langCode + '][]"]').val() || []
                    };

                    // SEO Analiz Sekmesi
                    seoData.seo_metas[langCode].analysis = {
                        seo_analysis: $('#seo-analysis-' + langCode + ' textarea[name="seo_analysis[' + langCode + ']"]').val() || ''
                    };
                });
                $('#seo_data_json').val(JSON.stringify(seoData));

                // time-input değerlerini al
                let timeValues = [];
                $('.time-repeater .time-input').each(function() {
                    let timeValue = $(this).val();
                    if (timeValue) {
                        timeValues.push(timeValue);
                    }
                });
                // JSON string'e çevir
                const timeJson = JSON.stringify(timeValues);
                // Hidden input'a yaz
                $('#tour_times_json').val(timeJson);

                // Ekstraları JSON'a dönüştürme
                let extrasData = $('.extras-repeater [data-repeater-item]').map(function() {
                    let itemData = {};
                    $(this).find('input, select').each(function() {
                        itemData[this.name] = $(this).val();
                    });
                    return itemData;
                }).get();

                // extrasData'yı temizleyip name attribute'ları düzelt
                let cleanedExtrasData = extrasData.map(item => {
                    let cleanedItem = {};
                    for (const key in item) {
                        if (item.hasOwnProperty(key)) {
                            const match = key.match(/extras\[\d+\]\[(.*)\]/);
                            if (match) {
                                cleanedItem[match[1]] = item[key];
                            }
                        }
                    }
                    return cleanedItem;
                });
                const extrasJson = JSON.stringify(cleanedExtrasData);
                $('#extras_json').val(extrasJson);

                // Diğer fiyatları JSON'a dönüştürme
                let otherPricesData = {
                    'single': {
                        'description': $('input[name="other_tour_prices[single][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[single][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[single][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[single][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[single][is_active]"]:checked').val() || 0
                    },
                    'couple': {
                        'description': $('input[name="other_tour_prices[couple][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[couple][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[couple][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[couple][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[couple][is_active]"]:checked').val() || 0
                    },
                    'family': {
                        'description': $('input[name="other_tour_prices[family][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[family][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[family][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[family][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[family][is_active]"]:checked').val() || 0
                    }
                };

                const otherPricesJson = JSON.stringify(otherPricesData);
                $('#other_prices_json').val(otherPricesJson);

                // Boş ekstraları kaldırma
                $('.extras-repeater [data-repeater-item]').each(function(index) {
                    const serviceInput = $(this).find('input[name="service_name"]');
                    const serviceVal = serviceInput.val() || '';
                    if (!serviceVal.trim()) {
                        $(this).remove();
                    } else {
                        // Doğru name'i ekledik
                        serviceInput.attr('name', 'extras[' + index + '][service_name]');
                        $(this).find('input[name="price_euro"]').attr('name', 'extras[' + index + '][price_euro]');
                        $(this).find('input[name="price_dollar"]').attr('name', 'extras[' + index + '][price_dollar]');
                        $(this).find('input[name="price_sterlin"]').attr('name', 'extras[' + index + '][price_sterlin]');
                        $(this).find('input[name="is_active"]').attr('name', 'extras[' + index + '][is_active]');
                    }
                });
            });

            // removeSeoImage ve removeFacebookImage fonksiyonlarını global hale getir
            window.removeSeoImage = function(langCode) {
                var seoInput = $('#seoImageInput_' + langCode);
                var currentSeoImage = seoInput.data('current-image');
                var previewContainer = $('#seoImagePreview_' + langCode);
                if (currentSeoImage) {
                    var deleteSeoImage = $('#delete_seo_image').val();
                    if (deleteSeoImage) {
                        deleteSeoImage += ',' + currentSeoImage;
                    } else {
                        deleteSeoImage = currentSeoImage;
                    }
                    $('#delete_seo_image').val(deleteSeoImage);
                }
                seoInput.val('');
                previewContainer.html('');
            }

            window.removeFacebookImage = function(langCode) {
                var fbInput = $('#facebook_image_' + langCode);
                var currentFbImage = fbInput.data('current-image');
                var previewContainer = $('#facebookImagePreview_' + langCode);

                if (currentFbImage) {
                    var deleteFacebookImage = $('#delete_facebook_image').val();
                    if (deleteFacebookImage) {
                        deleteFacebookImage += ',' + currentFbImage;
                    } else {
                        deleteFacebookImage = currentFbImage;
                    }
                    $('#delete_facebook_image').val(deleteFacebookImage);
                }

                fbInput.val('');
                previewContainer.html('');
            }

            // Küçük Resim Silme Fonksiyonu
            window.removeSmallImage = function() {
                $('#smallImageInput').val('');
                $('#smallImagePreview').html('');
                $('#delete_small_image').val('1'); // Sunucuya küçük resmin silinmesi gerektiğini bildir
            }

            //  Facebook Resmi Önizlemesi için Fonksiyon
            function previewFacebookImage(input) {
                if (input.files && input.files[0]) {
                    var langCode = input.id.split('_').pop(); // Örneğin 'facebook_image_en' -> 'en'
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#facebookImagePreview_' + langCode).html(`
                        <div class="preview-container">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px;">
                            <div class="remove-image" onclick="removeFacebookImage('${langCode}')">x</div>
                        </div>
                    `);
                        // Yeni yüklenen resim için data-current-image'i güncelleyin
                        $('#facebook_image_' + langCode).data('current-image', '');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // SEO Resmi Önizlemesi için Fonksiyon
            function previewSeoImage(input) {
                if (input.files && input.files[0]) {
                    var langCode = input.id.split('_').pop(); // Örneğin 'seoImageInput_en' -> 'en'
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#seoImagePreview_' + langCode).html(`
                        <div class="preview-container">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px;">
                            <div class="remove-image" onclick="removeSeoImage('${langCode}')">x</div>
                        </div>
                    `);
                        // Yeni yüklenen resim için data-current-image'i güncelleyin
                        $('#seoImageInput_' + langCode).data('current-image', '');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // Fiyat tabını varsayılan olarak göster
            $('#bf-price-tab').tab('show');

            // Sayfa yüklendiğinde 'Fiyat' tabını ve içeriklerini düzgün şekilde aç
            $(window).on('load', function() {
                $('#bf-price-tab').tab('show'); // Sayfa yüklendiğinde 'Fiyat' tabını aktif et
                $('#bf-price').addClass('show active'); // 'Fiyat' tabının içeriğini göster
            });

            // Tablar arasında geçiş yapılırken içeriğin doğru şekilde görünmesini sağlıyoruz
            $('#bfTabContent').on('click', '.nav-link', function() {
                var targetTab = $(this).attr('data-bs-target');
                $(targetTab).addClass('show active').siblings('.tab-pane').removeClass('show active');
            });

            // Sayfa yüklendiğinde SEO Ayarları - Başlangıçta "Genel" tabını aktif et
            $(window).on('load', function() {
                $('#seoTabs-<?php echo $lc; ?> #seo-general-tab-<?php echo $lc; ?>').tab('show');
                $('#seo-general-<?php echo $lc; ?>').addClass('show active');
            });

            // Dil Değişikliğinde İçeriği ve SEO Formunu Güncelle
            function handleLanguageChange() {
                var selectedLang = $('#languageSwitcher').val();

                // İçerik Tablarını Güncelle
                $('.tab-pane').removeClass('show active');
                $('#' + selectedLang).addClass('show active');

                // SEO Tablarını Güncelle
                $('.tab-content[id^="settingsTabContent-"]').hide();
                $('#settingsTabContent-' + selectedLang).show();
                $('#seoTabs-' + selectedLang + ' .nav-link:first').tab('show');

                // Fiyat tabını aktif et
                $('#bf-price-tab').tab('show');
                $('#bf-price').addClass('show active');
            }

            $('#languageSwitcher').on('change', handleLanguageChange);

            // Sayfa Yüklendiğinde İlk Dil İçeriğini ve SEO Formunu Göster
            var initialLang = $('#languageSwitcher').val();
            $('.tab-pane').removeClass('show active');
            $('#' + initialLang).addClass('show active');
            $('.tab-content[id^="settingsTabContent-"]').hide();
            $('#settingsTabContent-' + initialLang).show();
            $('#seoTabs-' + initialLang + ' .nav-link:first').tab('show');

            // Sayfa yüklendiğinde SEO Ayarları - Başlangıçta "Genel" tabını aktif et
            $('#seoTabs-' + initialLang + ' #seo-general-tab-' + initialLang).tab('show');
            $('#seo-general-' + initialLang).addClass('show active');

            // Form Gönderimi Öncesi İşlemler
            $('#tourForm').on('submit', function(e) {
                // Öncelikle, formun doğru şekilde çalışması için gerekli tüm işlemleri yapın
                let seoData = {
                    seo_metas: {}
                };

                $('#languageTabsContent .tab-pane').each(function() {
                    var langCode = $(this).attr('id');
                    seoData.seo_metas[langCode] = {};

                    // Genel Sekmesi
                    seoData.seo_metas[langCode].general = {
                        seo_title: $('#seo-general-' + langCode + ' input[name="seo_title[' + langCode + ']"]').val() || '',
                        seo_description: $('#seo-general-' + langCode + ' textarea[name="seo_description[' + langCode + ']"]').val() || '',
                        seo_keywords: $('#seo-general-' + langCode + ' input[name="seo_keywords[' + langCode + ']"]').val() || '',
                        seo_image: $('#seo-general-' + langCode + ' input[name="seo_image[' + langCode + ']"]').val() ? $('#seo-general-' + langCode + ' input[name="seo_image[' + langCode + ']"]').val().split('\\').pop() : ''
                    };

                    // Sosyal Medya Sekmesi
                    seoData.seo_metas[langCode].social = {
                        facebook_title: $('#seo-social-' + langCode + ' input[name="facebook_title[' + langCode + ']"]').val() || '',
                        facebook_description: $('#seo-social-' + langCode + ' textarea[name="facebook_description[' + langCode + ']"]').val() || '',
                        facebook_image: $('#seo-social-' + langCode + ' input[name="facebook_image[' + langCode + ']"]').val() ? $('#seo-social-' + langCode + ' input[name="facebook_image[' + langCode + ']"]').val().split('\\').pop() : ''
                    };

                    // Gelişmiş Sekmesi
                    seoData.seo_metas[langCode].advanced = {
                        robots_meta: $('#seo-advanced-' + langCode + ' input[name="robots_meta[' + langCode + ']"]').val() || '',
                        canonical_url: $('#seo-advanced-' + langCode + ' input[name="canonical_url[' + langCode + ']"]').val() || '',
                        breadcrumb_title: $('#seo-advanced-' + langCode + ' input[name="breadcrumb_title[' + langCode + ']"]').val() || ''
                    };

                    // Schema Sekmesi
                    seoData.seo_metas[langCode].schema = {
                        schema_data: $('#schema-' + langCode + ' select[name="schema_data[' + langCode + '][]"]').val() || []
                    };

                    // SEO Analiz Sekmesi
                    seoData.seo_metas[langCode].analysis = {
                        seo_analysis: $('#seo-analysis-' + langCode + ' textarea[name="seo_analysis[' + langCode + ']"]').val() || ''
                    };
                });
                $('#seo_data_json').val(JSON.stringify(seoData));

                // time-input değerlerini al
                let timeValues = [];
                $('.time-repeater .time-input').each(function() {
                    let timeValue = $(this).val();
                    if (timeValue) {
                        timeValues.push(timeValue);
                    }
                });
                // JSON string'e çevir
                const timeJson = JSON.stringify(timeValues);
                // Hidden input'a yaz
                $('#tour_times_json').val(timeJson);

                // Ekstraları JSON'a dönüştürme
                let extrasData = $('.extras-repeater [data-repeater-item]').map(function() {
                    let itemData = {};
                    $(this).find('input, select').each(function() {
                        itemData[this.name] = $(this).val();
                    });
                    return itemData;
                }).get();

                // extrasData'yı temizleyip name attribute'ları düzelt
                let cleanedExtrasData = extrasData.map(item => {
                    let cleanedItem = {};
                    for (const key in item) {
                        if (item.hasOwnProperty(key)) {
                            const match = key.match(/extras\[\d+\]\[(.*)\]/);
                            if (match) {
                                cleanedItem[match[1]] = item[key];
                            }
                        }
                    }
                    return cleanedItem;
                });
                const extrasJson = JSON.stringify(cleanedExtrasData);
                $('#extras_json').val(extrasJson);

                // Diğer fiyatları JSON'a dönüştürme
                let otherPricesData = {
                    'single': {
                        'description': $('input[name="other_tour_prices[single][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[single][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[single][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[single][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[single][is_active]"]:checked').val() || 0
                    },
                    'couple': {
                        'description': $('input[name="other_tour_prices[couple][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[couple][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[couple][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[couple][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[couple][is_active]"]:checked').val() || 0
                    },
                    'family': {
                        'description': $('input[name="other_tour_prices[family][description]"]').val(),
                        'price_euro': $('input[name="other_tour_prices[family][price_euro]"]').val(),
                        'price_dollar': $('input[name="other_tour_prices[family][price_dollar]"]').val(),
                        'price_sterlin': $('input[name="other_tour_prices[family][price_sterlin]"]').val(),
                        'is_active': $('input[name="other_tour_prices[family][is_active]"]:checked').val() || 0
                    }
                };

                const otherPricesJson = JSON.stringify(otherPricesData);
                $('#other_prices_json').val(otherPricesJson);

                // Boş ekstraları kaldırma
                $('.extras-repeater [data-repeater-item]').each(function(index) {
                    const serviceInput = $(this).find('input[name="service_name"]');
                    const serviceVal = serviceInput.val() || '';
                    if (!serviceVal.trim()) {
                        $(this).remove();
                    } else {
                        // Doğru name'i ekledik
                        serviceInput.attr('name', 'extras[' + index + '][service_name]');
                        $(this).find('input[name="price_euro"]').attr('name', 'extras[' + index + '][price_euro]');
                        $(this).find('input[name="price_dollar"]').attr('name', 'extras[' + index + '][price_dollar]');
                        $(this).find('input[name="price_sterlin"]').attr('name', 'extras[' + index + '][price_sterlin]');
                        $(this).find('input[name="is_active"]').attr('name', 'extras[' + index + '][is_active]');
                    }
                });
            });

            // removeSeoImage ve removeFacebookImage fonksiyonlarını global hale getir
            window.removeSeoImage = function(langCode) {
                var seoInput = $('#seoImageInput_' + langCode);
                var currentSeoImage = seoInput.data('current-image');
                var previewContainer = $('#seoImagePreview_' + langCode);
                if (currentSeoImage) {
                    var deleteSeoImage = $('#delete_seo_image').val();
                    if (deleteSeoImage) {
                        deleteSeoImage += ',' + currentSeoImage;
                    } else {
                        deleteSeoImage = currentSeoImage;
                    }
                    $('#delete_seo_image').val(deleteSeoImage);
                }
                seoInput.val('');
                previewContainer.html('');
            }

            window.removeFacebookImage = function(langCode) {
                var fbInput = $('#facebook_image_' + langCode);
                var currentFbImage = fbInput.data('current-image');
                var previewContainer = $('#facebookImagePreview_' + langCode);

                if (currentFbImage) {
                    var deleteFacebookImage = $('#delete_facebook_image').val();
                    if (deleteFacebookImage) {
                        deleteFacebookImage += ',' + currentFbImage;
                    } else {
                        deleteFacebookImage = currentFbImage;
                    }
                    $('#delete_facebook_image').val(deleteFacebookImage);
                }

                fbInput.val('');
                previewContainer.html('');
            }

            // Küçük Resim Silme Fonksiyonu
            window.removeSmallImage = function() {
                $('#smallImageInput').val('');
                $('#smallImagePreview').html('');
                $('#delete_small_image').val('1'); // Sunucuya küçük resmin silinmesi gerektiğini bildir
            }

            //  Facebook Resmi Önizlemesi için Fonksiyon
            function previewFacebookImage(input) {
                if (input.files && input.files[0]) {
                    var langCode = input.id.split('_').pop(); // Örneğin 'facebook_image_en' -> 'en'
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#facebookImagePreview_' + langCode).html(`
                        <div class="preview-container">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px;">
                            <div class="remove-image" onclick="removeFacebookImage('${langCode}')">x</div>
                        </div>
                    `);
                        // Yeni yüklenen resim için data-current-image'i güncelleyin
                        $('#facebook_image_' + langCode).data('current-image', '');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // SEO Resmi Önizlemesi için Fonksiyon
            function previewSeoImage(input) {
                if (input.files && input.files[0]) {
                    var langCode = input.id.split('_').pop(); // Örneğin 'seoImageInput_en' -> 'en'
                    var reader = new FileReader();

                    reader.onload = function(e) {
                        $('#seoImagePreview_' + langCode).html(`
                        <div class="preview-container">
                            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px;">
                            <div class="remove-image" onclick="removeSeoImage('${langCode}')">x</div>
                        </div>
                    `);
                        // Yeni yüklenen resim için data-current-image'i güncelleyin
                        $('#seoImageInput_' + langCode).data('current-image', '');
                    }

                    reader.readAsDataURL(input.files[0]);
                }
            }

            // Fiyat tabını varsayılan olarak göster
            $('#bf-price-tab').tab('show');

            // Sayfa yüklendiğinde 'Fiyat' tabını ve içeriklerini düzgün şekilde aç
            $(window).on('load', function() {
                $('#bf-price-tab').tab('show'); // Sayfa yüklendiğinde 'Fiyat' tabını aktif et
                $('#bf-price').addClass('show active'); // 'Fiyat' tabının içeriğini göster
            });
        });
    </script>




</body>

</html>
