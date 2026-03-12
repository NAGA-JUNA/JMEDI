<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('home_sections');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $earlyAction = $_POST['action'] ?? '';
    if ($earlyAction === 'hero_delete' && isset($_POST['delete_id'])) {
        $pdo->prepare("DELETE FROM hero_slides WHERE id = :id")->execute([':id' => (int)$_POST['delete_id']]);
        header('Location: /admin/home-sections.php?tab=heroSlidesTab&msg=deleted');
        exit;
    }
    if ($earlyAction === 'hero_toggle' && isset($_POST['toggle_id'])) {
        $pdo->prepare("UPDATE hero_slides SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = :id")
            ->execute([':id' => (int)$_POST['toggle_id']]);
        header('Location: /admin/home-sections.php?tab=heroSlidesTab&msg=toggled');
        exit;
    }
    if ($earlyAction === 'quick_toggle_section') {
        $allowedKeys = ['hero_slider','info_strip','about_section','why_choose_us','departments','doctors','our_videos','cta_checkup','appointment','process','stats','testimonials','blog','location','cta_ready'];
        $sKey = trim($_POST['section_key'] ?? '');
        if ($sKey && in_array($sKey, $allowedKeys, true)) {
            $vis = getHomeSection($pdo, 'section_visibility') ?: [];
            $vis[$sKey] = !empty($_POST['visible']) ? true : false;
            saveHomeSection($pdo, 'section_visibility', $vis);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}

$pageTitle = 'Home Page Sections';
require_once __DIR__ . '/../includes/admin_header.php';
requirePermission('home_sections');

$success = $error = '';
$infoStrip = getHomeSection($pdo, 'info_strip');
$aboutSection = getHomeSection($pdo, 'about_section');
$wcuSection   = getHomeSection($pdo, 'why_choose_us') ?: [
    'label'=>'WHY CHOOSE US','title'=>'Why Choose Us For Your Health Care Needs',
    'experience_number'=>'18+','experience_label'=>'YEARS','photo1'=>'','photo2'=>'',
    'float_icon'=>'fas fa-chart-bar',
    'features'=>[
        ['icon'=>'fas fa-trophy','title'=>'More Experience','description'=>'We offer a range of health services to meet all your needs.'],
        ['icon'=>'fas fa-hands-helping','title'=>'Seamless Care','description'=>'We offer a range of health services to meet all your needs.'],
        ['icon'=>'fas fa-shield-alt','title'=>'The Right Answers','description'=>'We offer a range of health services to meet all your needs.'],
        ['icon'=>'fas fa-star','title'=>'Unparalleled Expertise','description'=>'We offer a range of health services to meet all your needs.'],
    ]
];
$locationSection = getHomeSection($pdo, 'location_section');
$sectionVisibility = getHomeSection($pdo, 'section_visibility');

/* Helper: renders an instant-save visibility toggle for use in each section tab header */
$visToggle = function(string $key) use (&$sectionVisibility): string {
    $on  = !empty($sectionVisibility[$key]);
    $uid = 'qt_' . $key;
    return '<div class="form-check form-switch ms-2 mb-0 d-flex align-items-center gap-1" title="Toggle section visibility on/off">'
         . '<input class="form-check-input" type="checkbox" id="' . $uid . '" role="switch"' . ($on ? ' checked' : '') . ' onchange="quickToggleSection(\'' . htmlspecialchars($key, ENT_QUOTES) . '\',this)" style="cursor:pointer;">'
         . '<label class="form-check-label small text-muted fw-normal" for="' . $uid . '" style="cursor:pointer;white-space:nowrap;">' . ($on ? 'Visible' : 'Hidden') . '</label>'
         . '</div>';
};

$ctaCheckup = getHomeSection($pdo, 'cta_checkup');
$appointmentSection = getHomeSection($pdo, 'appointment_section');
$processSection = getHomeSection($pdo, 'process_section');
$statsSection = getHomeSection($pdo, 'stats_section');
$ctaReady = getHomeSection($pdo, 'cta_ready');
$allSlides = getHeroSlides($pdo);
$heroAction = $_GET['action'] ?? '';
$heroId = (int)($_GET['id'] ?? 0);
$editSlide = null;
if ($heroAction === 'edit' && $heroId) {
    $editSlide = getHeroSlide($pdo, $heroId);
}

$videosSection = getHomeSection($pdo, 'our_videos') ?: ['title' => 'Our Latest Videos', 'subtitle' => 'Watch health tips, facility tours and expert talks', 'videos' => []];
if (empty($videosSection['videos'])) $videosSection['videos'] = [];

$allSections = [
    'hero_slider' => ['label' => 'Hero Slider', 'icon' => 'fa-images', 'color' => '#0D6EFD'],
    'info_strip' => ['label' => 'Info Strip', 'icon' => 'fa-columns', 'color' => '#6f42c1'],
    'about_section' => ['label' => 'About Us', 'icon' => 'fa-info-circle', 'color' => '#20C997'],
    'why_choose_us' => ['label' => 'Why Choose Us', 'icon' => 'fa-shield-alt', 'color' => '#0b1f4f'],
    'departments' => ['label' => 'Departments', 'icon' => 'fa-hospital', 'color' => '#0dcaf0'],
    'doctors' => ['label' => 'Doctors', 'icon' => 'fa-user-md', 'color' => '#198754'],
    'our_videos' => ['label' => 'Our Videos', 'icon' => 'fa-play-circle', 'color' => '#ff0000'],
    'cta_checkup' => ['label' => 'CTA - Check-up', 'icon' => 'fa-stethoscope', 'color' => '#fd7e14'],
    'appointment' => ['label' => 'Appointment Form', 'icon' => 'fa-calendar-check', 'color' => '#0a58ca'],
    'process' => ['label' => 'Working Process', 'icon' => 'fa-cogs', 'color' => '#6610f2'],
    'stats' => ['label' => 'Statistics', 'icon' => 'fa-chart-bar', 'color' => '#dc3545'],
    'testimonials' => ['label' => 'Testimonials', 'icon' => 'fa-comments', 'color' => '#ffc107'],
    'blog' => ['label' => 'Latest News', 'icon' => 'fa-newspaper', 'color' => '#20c997'],
    'location_section' => ['label' => 'Location & Map', 'icon' => 'fa-map-marked-alt', 'color' => '#0f2137'],
    'cta_ready' => ['label' => 'CTA - Get Started', 'icon' => 'fa-rocket', 'color' => '#1aae82'],
];

if (empty($sectionVisibility)) {
    $sectionVisibility = array_fill_keys(array_keys($allSections), true);
}

if (!isset($infoStrip['items'])) {
    $infoStrip = ['items' => [
        ['title' => 'Request Appointment', 'subtitle' => 'Book a visit online', 'icon' => 'fa-calendar-check', 'link' => '/public/appointment.php', 'color' => '#0D6EFD'],
        ['title' => 'Find Doctors', 'subtitle' => 'Expert specialists', 'icon' => 'fa-user-md', 'link' => '/public/doctors.php', 'color' => '#20C997'],
        ['title' => 'Find Locations', 'subtitle' => 'Visit our hospital', 'icon' => 'fa-map-marker-alt', 'link' => '/public/contact.php', 'color' => '#6f42c1'],
        ['title' => 'Emergency', 'subtitle' => '+1(800) 911-0000', 'icon' => 'fa-ambulance', 'link' => '', 'color' => '#dc3545']
    ]];
}

if (empty($aboutSection)) {
    $aboutSection = [
        'subtitle' => 'About Us',
        'title' => 'Welcome To JMedi Central Hospital',
        'description' => '',
        'image' => '',
        'experience_years' => '25+',
        'experience_label' => 'Years of Experience',
        'features' => ['Advanced Medical Technology', 'Certified & Experienced Doctors', '24/7 Emergency Support', 'Comprehensive Health Packages'],
        'button_text' => 'Discover More',
        'button_link' => '/public/departments.php'
    ];
}

if (empty($locationSection)) {
    $locationSection = [
        'title' => 'Our Location',
        'subtitle' => 'Find Us',
        'description' => 'Visit us at our main hospital campus.',
        'address' => '',
        'phone' => '',
        'email' => '',
        'hours' => 'Mon - Sat: 8:00 AM - 7:00 PM',
        'map_embed' => ''
    ];
}

if (empty($ctaCheckup)) {
    $ctaCheckup = [
        'heading' => 'Need a Doctor for Check-up?',
        'subtitle' => 'Just make an appointment and you\'re done!',
        'button_text' => 'Make Appointment',
        'button_link' => '/public/appointment.php'
    ];
}

if (empty($appointmentSection)) {
    $appointmentSection = [
        'badge_text' => 'Appointment',
        'heading' => 'Book Your<br>Appointment',
        'description' => 'Schedule your visit with our specialists. Fill out the form and we\'ll confirm your appointment within 24 hours.'
    ];
}

if (empty($processSection)) {
    $processSection = [
        'subtitle' => 'Working Process',
        'heading' => 'How It Helps You Stay Healthy',
        'steps' => [
            ['icon' => 'fa-calendar-check', 'title' => 'Book Appointment', 'description' => 'Schedule your visit online easily with our booking system'],
            ['icon' => 'fa-stethoscope', 'title' => 'Consultation', 'description' => 'Meet with experienced doctors for thorough evaluation'],
            ['icon' => 'fa-prescription', 'title' => 'Treatment Plan', 'description' => 'Receive personalized treatment plans tailored for you'],
            ['icon' => 'fa-smile-beam', 'title' => 'Get Healthy', 'description' => 'Recover and enjoy a healthy, happy life with ongoing care']
        ]
    ];
}

if (empty($statsSection)) {
    $statsSection = [
        'items' => [
            ['icon' => 'fa-hospital', 'number' => '25', 'suffix' => '+', 'label' => 'Years of Experience'],
            ['icon' => 'fa-user-md', 'number' => 'auto', 'suffix' => '', 'label' => 'Medical Specialists'],
            ['icon' => 'fa-procedures', 'number' => '13', 'suffix' => '', 'label' => 'Modern Rooms'],
            ['icon' => 'fa-smile', 'number' => '1500', 'suffix' => '+', 'label' => 'Happy Patients']
        ]
    ];
}

if (empty($ctaReady)) {
    $ctaReady = [
        'heading' => 'Ready to Get Started?',
        'description' => 'Our team of experienced doctors is ready to help you. Book your appointment today and take the first step towards better health.',
        'button1_text' => 'Book Appointment',
        'button1_link' => '/public/appointment.php',
        'button2_text' => 'Contact Us',
        'button2_link' => '/public/contact.php'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_visibility') {
        $newVisibility = [];
        foreach (array_keys($allSections) as $key) {
            $newVisibility[$key] = isset($_POST['section_visible'][$key]);
        }
        $sectionVisibility = $newVisibility;
        if (saveHomeSection($pdo, 'section_visibility', $sectionVisibility)) {
            $success = 'Section visibility updated successfully!';
        } else {
            $error = 'Failed to update section visibility.';
        }
    }

    if ($action === 'save_info_strip') {
        $items = [];
        $titles = $_POST['strip_title'] ?? [];
        $subtitles = $_POST['strip_subtitle'] ?? [];
        $icons = $_POST['strip_icon'] ?? [];
        $links = $_POST['strip_link'] ?? [];
        $colors = $_POST['strip_color'] ?? [];
        for ($i = 0; $i < count($titles); $i++) {
            if (!empty(trim($titles[$i]))) {
                $items[] = [
                    'title' => trim($titles[$i]),
                    'subtitle' => trim($subtitles[$i] ?? ''),
                    'icon' => trim($icons[$i] ?? 'fa-circle'),
                    'link' => trim($links[$i] ?? ''),
                    'color' => trim($colors[$i] ?? '#0D6EFD')
                ];
            }
        }
        $infoStrip['items'] = $items;
        if (saveHomeSection($pdo, 'info_strip', $infoStrip)) {
            $success = 'Info strip updated successfully!';
        } else {
            $error = 'Failed to update info strip.';
        }
    }

    if ($action === 'save_about') {
        if (!empty($_FILES['about_image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/sections/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['about_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['about_image']['tmp_name']);
            finfo_close($finfo);
            if (in_array($ext, $allowed) && in_array($mime, $allowedMime)) {
                $filename = uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['about_image']['tmp_name'], $uploadDir . $filename)) {
                    $aboutSection['image'] = '/assets/uploads/sections/' . $filename;
                }
            } else {
                $error = 'Invalid image format. Allowed: jpg, png, gif, webp';
            }
        }

        $aboutSection['subtitle'] = trim($_POST['about_subtitle'] ?? 'About Us');
        $aboutSection['title'] = trim($_POST['about_title'] ?? '');
        $aboutSection['description'] = trim($_POST['about_description'] ?? '');
        $aboutSection['experience_years'] = trim($_POST['about_experience_years'] ?? '25+');
        $aboutSection['experience_label'] = trim($_POST['about_experience_label'] ?? 'Years of Experience');
        $aboutSection['button_text'] = trim($_POST['about_button_text'] ?? 'Discover More');
        $aboutSection['button_link'] = trim($_POST['about_button_link'] ?? '/public/departments.php');

        $features = array_filter(array_map('trim', $_POST['about_features'] ?? []));
        $aboutSection['features'] = array_values($features);

        if (saveHomeSection($pdo, 'about_section', $aboutSection)) {
            $success = $success ?: 'About section updated successfully!';
        } else {
            $error = 'Failed to update about section.';
        }
    }

    if ($action === 'save_wcu') {
        $uploadDir = __DIR__ . '/../assets/uploads/sections/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $allowed     = ['jpg','jpeg','png','gif','webp'];
        $allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];
        foreach (['wcu_photo1' => 'photo1', 'wcu_photo2' => 'photo2'] as $fileKey => $dataKey) {
            if (!empty($_FILES[$fileKey]['name'])) {
                $ext  = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES[$fileKey]['tmp_name']);
                finfo_close($finfo);
                if (in_array($ext, $allowed) && in_array($mime, $allowedMime)) {
                    $fname = uniqid('wcu_') . '.' . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $fname)) {
                        $wcuSection[$dataKey] = '/assets/uploads/sections/' . $fname;
                    }
                } else {
                    $error = 'Invalid image. Allowed: jpg, png, gif, webp.';
                }
            }
        }
        $wcuSection['label']             = trim($_POST['wcu_label'] ?? 'WHY CHOOSE US');
        $wcuSection['title']             = trim($_POST['wcu_title'] ?? '');
        $wcuSection['experience_number'] = trim($_POST['wcu_exp_num'] ?? '18+');
        $wcuSection['experience_label']  = trim($_POST['wcu_exp_label'] ?? 'YEARS');
        $wcuSection['float_icon']        = trim($_POST['wcu_float_icon'] ?? 'fas fa-chart-bar');
        $feats = [];
        $fIcons = $_POST['wcu_feat_icon']  ?? [];
        $fTitles= $_POST['wcu_feat_title'] ?? [];
        $fDescs = $_POST['wcu_feat_desc']  ?? [];
        for ($i = 0; $i < 4; $i++) {
            $feats[] = [
                'icon'        => trim($fIcons[$i]  ?? 'fas fa-star'),
                'title'       => trim($fTitles[$i] ?? ''),
                'description' => trim($fDescs[$i]  ?? ''),
            ];
        }
        $wcuSection['features'] = $feats;
        if (saveHomeSection($pdo, 'why_choose_us', $wcuSection)) {
            $success = 'Why Choose Us section updated!';
        } else {
            $error = 'Failed to update Why Choose Us section.';
        }
    }

    if ($action === 'save_location') {
        $mapInput = trim($_POST['location_map_embed'] ?? '');
        if (!empty($mapInput) && !str_starts_with($mapInput, 'https://www.google.com/maps/embed')) {
            if (preg_match('/src=["\']([^"\']+)["\']/', $mapInput, $matches)) {
                $mapInput = $matches[1];
            }
        }

        $locationSection['title'] = trim($_POST['location_title'] ?? 'Our Location');
        $locationSection['subtitle'] = trim($_POST['location_subtitle'] ?? 'Find Us');
        $locationSection['description'] = trim($_POST['location_description'] ?? '');
        $locationSection['address'] = trim($_POST['location_address'] ?? '');
        $locationSection['phone'] = trim($_POST['location_phone'] ?? '');
        $locationSection['email'] = trim($_POST['location_email'] ?? '');
        $locationSection['hours'] = trim($_POST['location_hours'] ?? '');
        $locationSection['map_embed'] = $mapInput;

        if (saveHomeSection($pdo, 'location_section', $locationSection)) {
            $success = 'Location section updated successfully!';
        } else {
            $error = 'Failed to update location section.';
        }
    }

    if ($action === 'save_cta_checkup') {
        $ctaCheckup = [
            'heading' => trim($_POST['cta_heading'] ?? ''),
            'subtitle' => trim($_POST['cta_subtitle'] ?? ''),
            'button_text' => trim($_POST['cta_button_text'] ?? 'Make Appointment'),
            'button_link' => trim($_POST['cta_button_link'] ?? '/public/appointment.php')
        ];
        if (saveHomeSection($pdo, 'cta_checkup', $ctaCheckup)) {
            $success = 'CTA Check-up section updated!';
        } else { $error = 'Failed to save.'; }
    }

    if ($action === 'save_appointment') {
        $appointmentSection = [
            'badge_text' => trim($_POST['appt_badge'] ?? 'Appointment'),
            'heading' => trim($_POST['appt_heading'] ?? ''),
            'description' => trim($_POST['appt_description'] ?? '')
        ];
        if (saveHomeSection($pdo, 'appointment_section', $appointmentSection)) {
            $success = 'Appointment section updated!';
        } else { $error = 'Failed to save.'; }
    }

    if ($action === 'save_process') {
        $steps = [];
        $pIcons = $_POST['process_icon'] ?? [];
        $pTitles = $_POST['process_title'] ?? [];
        $pDescs = $_POST['process_desc'] ?? [];
        for ($i = 0; $i < count($pTitles); $i++) {
            if (!empty(trim($pTitles[$i]))) {
                $steps[] = [
                    'icon' => trim($pIcons[$i] ?? 'fa-circle'),
                    'title' => trim($pTitles[$i]),
                    'description' => trim($pDescs[$i] ?? '')
                ];
            }
        }
        $processSection = [
            'subtitle' => trim($_POST['process_subtitle'] ?? 'Working Process'),
            'heading' => trim($_POST['process_heading'] ?? ''),
            'steps' => $steps
        ];
        if (saveHomeSection($pdo, 'process_section', $processSection)) {
            $success = 'Working Process section updated!';
        } else { $error = 'Failed to save.'; }
    }

    if ($action === 'save_stats') {
        $items = [];
        $sIcons = $_POST['stat_icon'] ?? [];
        $sNumbers = $_POST['stat_number'] ?? [];
        $sSuffixes = $_POST['stat_suffix'] ?? [];
        $sLabels = $_POST['stat_label'] ?? [];
        for ($i = 0; $i < count($sLabels); $i++) {
            if (!empty(trim($sLabels[$i]))) {
                $items[] = [
                    'icon' => trim($sIcons[$i] ?? 'fa-chart-bar'),
                    'number' => trim($sNumbers[$i] ?? '0'),
                    'suffix' => trim($sSuffixes[$i] ?? ''),
                    'label' => trim($sLabels[$i])
                ];
            }
        }
        $statsSection = ['items' => $items];
        if (saveHomeSection($pdo, 'stats_section', $statsSection)) {
            $success = 'Statistics section updated!';
        } else { $error = 'Failed to save.'; }
    }

    if ($action === 'save_cta_ready') {
        $ctaReady = [
            'heading' => trim($_POST['ctar_heading'] ?? ''),
            'description' => trim($_POST['ctar_description'] ?? ''),
            'button1_text' => trim($_POST['ctar_btn1_text'] ?? 'Book Appointment'),
            'button1_link' => trim($_POST['ctar_btn1_link'] ?? '/public/appointment.php'),
            'button2_text' => trim($_POST['ctar_btn2_text'] ?? 'Contact Us'),
            'button2_link' => trim($_POST['ctar_btn2_link'] ?? '/public/contact.php')
        ];
        if (saveHomeSection($pdo, 'cta_ready', $ctaReady)) {
            $success = 'CTA Ready section updated!';
        } else { $error = 'Failed to save.'; }
    }

    if ($action === 'save_video_settings') {
        $videosSection['title'] = trim($_POST['vid_title'] ?? 'Our Latest Videos');
        $videosSection['subtitle'] = trim($_POST['vid_subtitle'] ?? '');
        if (saveHomeSection($pdo, 'our_videos', $videosSection)) {
            $success = 'Video section heading updated!';
        } else { $error = 'Failed to save.'; }
    }

    if ($action === 'add_video') {
        $rawUrl = trim($_POST['video_url'] ?? '');
        $vidTitle = trim($_POST['video_title'] ?? 'Untitled Video');
        preg_match('/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $rawUrl, $m);
        $vidId = $m[1] ?? $rawUrl;
        if (strlen($vidId) === 11) {
            $videosSection['videos'][] = ['id' => $vidId, 'title' => $vidTitle, 'active' => true];
            if (saveHomeSection($pdo, 'our_videos', $videosSection)) {
                $success = 'Video added!';
            } else { $error = 'Failed to save.'; }
        } else {
            $error = 'Invalid YouTube URL or video ID.';
        }
    }

    if ($action === 'toggle_video') {
        $vidIdx = (int)($_POST['video_index'] ?? -1);
        if (isset($videosSection['videos'][$vidIdx])) {
            $videosSection['videos'][$vidIdx]['active'] = !($videosSection['videos'][$vidIdx]['active'] ?? true);
            saveHomeSection($pdo, 'our_videos', $videosSection);
            $success = 'Video status updated!';
        }
    }

    if ($action === 'delete_video') {
        $vidIdx = (int)($_POST['video_index'] ?? -1);
        if (isset($videosSection['videos'][$vidIdx])) {
            array_splice($videosSection['videos'], $vidIdx, 1);
            saveHomeSection($pdo, 'our_videos', $videosSection);
            $success = 'Video deleted!';
        }
    }

    if ($action === 'hero_save') {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? 'Learn More');
        $buttonLink = trim($_POST['button_link'] ?? '#');
        $overlayColor = trim($_POST['overlay_color'] ?? 'rgba(15,33,55,0.7)');
        $textAnimation = $_POST['text_animation'] ?? 'fadeIn';
        $transitionEffect = $_POST['transition_effect'] ?? 'slide';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);

        $bgImage = null;
        if (!empty($_FILES['background_image']['name'])) {
            $bgImage = uploadImage($_FILES['background_image'], 'uploads/slides');
        }

        if (empty($title)) {
            $error = 'Slide title is required.';
        } else {
            if (isset($_POST['slide_id']) && $_POST['slide_id']) {
                $sql = "UPDATE hero_slides SET title=:title, subtitle=:subtitle, description=:description, button_text=:btn_text, button_link=:btn_link, overlay_color=:overlay, text_animation=:anim, transition_effect=:trans, sort_order=:sort, status=:status";
                $data = [
                    ':title' => $title, ':subtitle' => $subtitle, ':description' => $description,
                    ':btn_text' => $buttonText, ':btn_link' => $buttonLink, ':overlay' => $overlayColor,
                    ':anim' => $textAnimation, ':trans' => $transitionEffect,
                    ':sort' => $sortOrder, ':status' => $status, ':id' => (int)$_POST['slide_id']
                ];
                if ($bgImage) {
                    $sql .= ", background_image=:img";
                    $data[':img'] = $bgImage;
                }
                $sql .= " WHERE id=:id";
                $pdo->prepare($sql)->execute($data);
                $success = 'Slide updated successfully.';
            } else {
                $pdo->prepare("INSERT INTO hero_slides (title, subtitle, description, button_text, button_link, background_image, overlay_color, text_animation, transition_effect, sort_order, status) VALUES (:title, :subtitle, :description, :btn_text, :btn_link, :img, :overlay, :anim, :trans, :sort, :status)")
                    ->execute([
                        ':title' => $title, ':subtitle' => $subtitle, ':description' => $description,
                        ':btn_text' => $buttonText, ':btn_link' => $buttonLink, ':img' => $bgImage,
                        ':overlay' => $overlayColor, ':anim' => $textAnimation, ':trans' => $transitionEffect,
                        ':sort' => $sortOrder, ':status' => $status
                    ]);
                $success = 'Slide created successfully.';
            }
            $heroAction = '';
            $allSlides = getHeroSlides($pdo);
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $success = 'Slide deleted.';
    if ($_GET['msg'] === 'toggled') $success = 'Slide status updated.';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-home me-2"></i>Home Page Sections</h4>
        <p class="text-muted mb-0">Manage all homepage sections</p>
    </div>
</div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= e($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-toggle-on me-2 text-primary"></i>Section Visibility</h6>
            <span class="badge bg-primary"><?= count(array_filter($sectionVisibility)) ?> / <?= count($allSections) ?> Active</span>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-3">Toggle sections on or off to control what appears on the homepage. Changes take effect immediately after saving.</p>
            <form method="POST" id="visibilityForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_visibility">
                <div class="row g-3">
                    <?php foreach ($allSections as $sKey => $sInfo): ?>
                    <div class="col-lg-3 col-md-4 col-6">
                        <div class="visibility-card <?= !empty($sectionVisibility[$sKey]) ? 'active' : '' ?>" onclick="toggleVisCard(this)">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="vis-icon" style="background:<?= $sInfo['color'] ?>;"><i class="fas <?= $sInfo['icon'] ?>"></i></div>
                                <span class="fw-semibold small"><?= e($sInfo['label']) ?></span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="section_visible[<?= $sKey ?>]" id="vis_<?= $sKey ?>" <?= !empty($sectionVisibility[$sKey]) ? 'checked' : '' ?> onchange="this.closest('.visibility-card').classList.toggle('active', this.checked); this.nextElementSibling.textContent = this.checked ? 'Visible' : 'Hidden'">
                                <label class="form-check-label small" for="vis_<?= $sKey ?>"><?= !empty($sectionVisibility[$sKey]) ? 'Visible' : 'Hidden' ?></label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Visibility</button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto" role="tablist" style="white-space:nowrap;">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#heroSlidesTab" type="button"><i class="fas fa-images me-1"></i> Hero Slides</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#infoStripTab" type="button"><i class="fas fa-columns me-1"></i> Info Strip</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#aboutTab" type="button"><i class="fas fa-info-circle me-1"></i> About</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#wcuTab" type="button"><i class="fas fa-shield-alt me-1"></i> Why Choose Us</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#departmentsTab" type="button"><i class="fas fa-hospital me-1"></i> Departments</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#doctorsTab" type="button"><i class="fas fa-user-md me-1"></i> Doctors</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ctaCheckupTab" type="button"><i class="fas fa-stethoscope me-1"></i> CTA Check-up</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#appointmentTab" type="button"><i class="fas fa-calendar-check me-1"></i> Appointment</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#processTab" type="button"><i class="fas fa-cogs me-1"></i> Process</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#statsTab" type="button"><i class="fas fa-chart-bar me-1"></i> Statistics</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#testimonialsTab" type="button"><i class="fas fa-comments me-1"></i> Testimonials</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#blogTab" type="button"><i class="fas fa-newspaper me-1"></i> Blog</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#locationTab" type="button"><i class="fas fa-map-marked-alt me-1"></i> Location</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ctaReadyTab" type="button"><i class="fas fa-rocket me-1"></i> CTA Ready</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#videosTab" type="button"><i class="fab fa-youtube me-1" style="color:#ff0000;"></i> Videos</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="heroSlidesTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0"><i class="fas fa-images me-2 text-primary"></i>Hero Slides (<?= count($allSlides) ?>)</h6>
                        <?= $visToggle('hero_slider') ?>
                    </div>
                    <?php if ($heroAction !== 'add' && $heroAction !== 'edit'): ?>
                    <a href="/admin/home-sections.php?action=add&tab=heroSlidesTab" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Add Slide</a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if ($heroAction === 'add' || $heroAction === 'edit'): ?>
                    <h6 class="mb-3"><i class="fas fa-<?= $editSlide ? 'edit' : 'plus' ?> me-2"></i><?= $editSlide ? 'Edit' : 'Add New' ?> Slide</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="hero_save">
                        <?php if ($editSlide): ?><input type="hidden" name="slide_id" value="<?= $editSlide['id'] ?>"><?php endif; ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" value="<?= e($editSlide['title'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Subtitle</label>
                                <input type="text" name="subtitle" class="form-control" value="<?= e($editSlide['subtitle'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= e($editSlide['description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button Text</label>
                                <input type="text" name="button_text" class="form-control" value="<?= e($editSlide['button_text'] ?? 'Learn More') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button Link</label>
                                <input type="text" name="button_link" class="form-control" value="<?= e($editSlide['button_link'] ?? '#') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Background Image</label>
                                <input type="file" name="background_image" class="form-control" accept="image/*">
                                <?php if (!empty($editSlide['background_image'])): ?>
                                <small class="text-muted">Current: <?= e(basename($editSlide['background_image'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Overlay Color</label>
                                <input type="text" name="overlay_color" class="form-control" value="<?= e($editSlide['overlay_color'] ?? 'rgba(15,33,55,0.7)') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Text Animation</label>
                                <select name="text_animation" class="form-select">
                                    <?php foreach (['fadeIn'=>'Fade In','slideUp'=>'Slide Up','slideLeft'=>'Slide Left','zoomIn'=>'Zoom In'] as $val=>$lbl): ?>
                                    <option value="<?= $val ?>" <?= ($editSlide['text_animation'] ?? 'fadeIn') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Transition Effect</label>
                                <select name="transition_effect" class="form-select">
                                    <?php foreach (['slide'=>'Slide','fade'=>'Fade','zoom'=>'Zoom'] as $val=>$lbl): ?>
                                    <option value="<?= $val ?>" <?= ($editSlide['transition_effect'] ?? 'slide') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="<?= (int)($editSlide['sort_order'] ?? 0) ?>" min="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="1" <?= ($editSlide['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= ($editSlide['status'] ?? 1) == 0 ? 'selected' : '' ?>>Disabled</option>
                                </select>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i>Save Slide</button>
                                <a href="/admin/home-sections.php?tab=heroSlidesTab" class="btn btn-secondary px-4">Cancel</a>
                            </div>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Order</th><th>Title</th><th>Animation</th><th>Image</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (empty($allSlides)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No slides yet. Click "Add Slide" to create one.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($allSlides as $s): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $s['sort_order'] ?></span></td>
                                    <td><strong><?= e($s['title']) ?></strong><?php if ($s['subtitle']): ?><br><small class="text-muted"><?= e($s['subtitle']) ?></small><?php endif; ?></td>
                                    <td><span class="badge bg-info"><?= e($s['text_animation']) ?></span></td>
                                    <td><?php if ($s['background_image']): ?><img src="<?= e($s['background_image']) ?>" alt="Slide" style="width:80px;height:45px;object-fit:cover;border-radius:6px;"><?php else: ?><span class="text-muted"><i class="fas fa-image"></i> Gradient</span><?php endif; ?></td>
                                    <td>
                                        <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="action" value="hero_toggle"><input type="hidden" name="toggle_id" value="<?= $s['id'] ?>">
                                            <button class="btn btn-sm <?= $s['status'] ? 'btn-success' : 'btn-outline-secondary' ?>"><i class="fas fa-<?= $s['status'] ? 'eye' : 'eye-slash' ?>"></i> <?= $s['status'] ? 'Active' : 'Disabled' ?></button>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="/admin/home-sections.php?action=edit&id=<?= $s['id'] ?>&tab=heroSlidesTab" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                        <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="action" value="hero_delete"><input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-trigger data-delete-label="the slide '<?= e($s['title']) ?>'"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="infoStripTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0"><i class="fas fa-columns me-2 text-primary"></i>Info Strip Items</h6>
                        <?= $visToggle('info_strip') ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStripItem()"><i class="fas fa-plus me-1"></i>Add Item</button>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">The info strip appears below the hero slider with 4 quick-access items. Each item has an icon, title, subtitle, link, and color.</p>
                    <form method="POST" id="infoStripForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_info_strip">
                        <div id="stripItems">
                            <?php foreach ($infoStrip['items'] as $i => $item): ?>
                            <div class="strip-item-row border rounded-3 p-3 mb-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0"><span class="badge bg-primary me-2"><?= $i + 1 ?></span>Item #<?= $i + 1 ?></h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.strip-item-row').remove()" title="Remove"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Title</label>
                                        <input type="text" name="strip_title[]" class="form-control" value="<?= e($item['title']) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Subtitle</label>
                                        <input type="text" name="strip_subtitle[]" class="form-control" value="<?= e($item['subtitle']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Link URL</label>
                                        <input type="text" name="strip_link[]" class="form-control" value="<?= e($item['link']) ?>" placeholder="/public/page.php">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Icon (FontAwesome class)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas <?= e($item['icon']) ?>"></i></span>
                                            <input type="text" name="strip_icon[]" class="form-control" value="<?= e($item['icon']) ?>" placeholder="fa-calendar-check">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Background Color</label>
                                        <div class="input-group">
                                            <input type="color" name="strip_color[]" class="form-control form-control-color" value="<?= e($item['color']) ?>" style="max-width:60px;">
                                            <input type="text" class="form-control" value="<?= e($item['color']) ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Info Strip</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="aboutTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>About Section</h6>
                    <?= $visToggle('about_section') ?>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" id="aboutForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_about">

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3"><i class="fas fa-image me-2 text-primary"></i>Section Image</h6>
                                <div class="about-image-preview mb-3 border rounded-3 overflow-hidden" style="height:250px;background:#f0f7ff;display:flex;align-items:center;justify-content:center;">
                                    <?php if (!empty($aboutSection['image'])): ?>
                                    <img src="<?= e($aboutSection['image']) ?>" alt="About" style="max-width:100%;max-height:100%;object-fit:cover;" id="aboutImgPreview">
                                    <?php else: ?>
                                    <div class="text-center text-muted" id="aboutImgPlaceholder">
                                        <i class="fas fa-cloud-upload-alt" style="font-size:3rem;opacity:0.3;"></i>
                                        <p class="mt-2 mb-0">Upload an image</p>
                                    </div>
                                    <img src="" alt="About" style="max-width:100%;max-height:100%;object-fit:cover;display:none;" id="aboutImgPreview">
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="about_image" class="form-control" accept="image/*" onchange="previewAboutImg(this)">
                                <?php if (!empty($aboutSection['image'])): ?>
                                <small class="text-muted">Current: <?= e($aboutSection['image']) ?></small>
                                <?php endif; ?>

                                <hr class="my-4">

                                <h6 class="fw-bold mb-3"><i class="fas fa-award me-2 text-primary"></i>Experience Badge</h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Number (e.g. 25+)</label>
                                        <input type="text" name="about_experience_years" class="form-control" value="<?= e($aboutSection['experience_years'] ?? '25+') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Label</label>
                                        <input type="text" name="about_experience_label" class="form-control" value="<?= e($aboutSection['experience_label'] ?? 'Years of Experience') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3"><i class="fas fa-pen me-2 text-primary"></i>Content</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Section Label</label>
                                    <input type="text" name="about_subtitle" class="form-control" value="<?= e($aboutSection['subtitle'] ?? 'About Us') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Heading</label>
                                    <input type="text" name="about_title" class="form-control" value="<?= e($aboutSection['title'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Description</label>
                                    <textarea name="about_description" class="form-control" rows="5"><?= e($aboutSection['description'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-3">

                                <h6 class="fw-bold mb-3"><i class="fas fa-list-ul me-2 text-primary"></i>Feature Checkmarks</h6>
                                <div id="featuresList">
                                    <?php foreach (($aboutSection['features'] ?? []) as $fi => $feature): ?>
                                    <div class="input-group mb-2 feature-row">
                                        <span class="input-group-text"><i class="fas fa-check text-success"></i></span>
                                        <input type="text" name="about_features[]" class="form-control" value="<?= e($feature) ?>">
                                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.feature-row').remove()"><i class="fas fa-times"></i></button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addFeature()"><i class="fas fa-plus me-1"></i>Add Feature</button>

                                <hr class="my-3">

                                <h6 class="fw-bold mb-3"><i class="fas fa-link me-2 text-primary"></i>Button</h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Button Text</label>
                                        <input type="text" name="about_button_text" class="form-control" value="<?= e($aboutSection['button_text'] ?? 'Discover More') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Button Link</label>
                                        <input type="text" name="about_button_link" class="form-control" value="<?= e($aboutSection['button_link'] ?? '/public/departments.php') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save About Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="wcuTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-shield-alt me-2 text-primary"></i>Why Choose Us Section</h6>
                    <?= $visToggle('why_choose_us') ?>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" id="wcuForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_wcu">
                        <div class="row g-4">

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Label <small class="text-muted">(e.g. WHY CHOOSE US)</small></label>
                                <input type="text" name="wcu_label" class="form-control" value="<?= e($wcuSection['label'] ?? 'WHY CHOOSE US') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Floating Card Icon <small class="text-muted">(FA class)</small></label>
                                <input type="text" name="wcu_float_icon" class="form-control" value="<?= e($wcuSection['float_icon'] ?? 'fas fa-chart-bar') ?>" placeholder="fas fa-chart-bar">
                                <div class="form-text">Preview: <i class="<?= e($wcuSection['float_icon'] ?? 'fas fa-chart-bar') ?> text-primary"></i></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Section Heading</label>
                                <input type="text" name="wcu_title" class="form-control" value="<?= e($wcuSection['title'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Experience Number <small class="text-muted">(e.g. 18+)</small></label>
                                <input type="text" name="wcu_exp_num" class="form-control" value="<?= e($wcuSection['experience_number'] ?? '18+') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Experience Label <small class="text-muted">(e.g. YEARS)</small></label>
                                <input type="text" name="wcu_exp_label" class="form-control" value="<?= e($wcuSection['experience_label'] ?? 'YEARS') ?>">
                            </div>

                            <div class="col-12"><hr class="my-1"><h6 class="mb-0 text-muted small text-uppercase fw-bold">Section Photos</h6></div>

                            <?php foreach ([['wcu_photo1','photo1','Main Photo (large, left side)'],['wcu_photo2','photo2','Secondary Photo (smaller, bottom-right overlap)']] as [$fk,$dk,$lbl]): ?>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold"><?= $lbl ?></label>
                                <?php if (!empty($wcuSection[$dk])): ?>
                                <div class="mb-2">
                                    <img src="<?= e($wcuSection[$dk]) ?>" style="max-height:120px;border-radius:8px;border:1px solid #dee2e6;" alt="Current">
                                    <div class="text-muted" style="font-size:0.75rem;">Current photo</div>
                                </div>
                                <?php endif; ?>
                                <input type="file" name="<?= $fk ?>" class="form-control" accept="image/*">
                                <div class="form-text">Leave empty to keep existing. Allowed: jpg, png, webp, gif.</div>
                            </div>
                            <?php endforeach; ?>

                            <div class="col-12"><hr class="my-1"><h6 class="mb-0 text-muted small text-uppercase fw-bold">Feature Cards (4)</h6></div>

                            <?php
                            $defaultFeatures = [
                                ['icon'=>'fas fa-trophy','title'=>'More Experience','description'=>'We offer a range of health services to meet all your needs.'],
                                ['icon'=>'fas fa-hands-helping','title'=>'Seamless Care','description'=>'We offer a range of health services to meet all your needs.'],
                                ['icon'=>'fas fa-shield-alt','title'=>'The Right Answers','description'=>'We offer a range of health services to meet all your needs.'],
                                ['icon'=>'fas fa-star','title'=>'Unparalleled Expertise','description'=>'We offer a range of health services to meet all your needs.'],
                            ];
                            $editFeats = array_values(array_slice(array_pad($wcuSection['features'] ?? [], 4, []), 0, 4));
                            for ($fi = 0; $fi < 4; $fi++):
                                $ef = $editFeats[$fi] + ($defaultFeatures[$fi] ?? []);
                            ?>
                            <div class="col-md-6">
                                <div class="card border rounded-3 p-3 h-100">
                                    <div class="fw-semibold small mb-2 text-primary">Card <?= str_pad($fi+1,2,'0',STR_PAD_LEFT) ?></div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">Icon <small class="text-muted">(FA class)</small></label>
                                        <input type="text" name="wcu_feat_icon[]" class="form-control form-control-sm" value="<?= e($ef['icon'] ?? 'fas fa-star') ?>" placeholder="fas fa-star">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">Title</label>
                                        <input type="text" name="wcu_feat_title[]" class="form-control form-control-sm" value="<?= e($ef['title'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold">Description</label>
                                        <textarea name="wcu_feat_desc[]" class="form-control form-control-sm" rows="2"><?= e($ef['description'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>

                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Why Choose Us Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="locationTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-map-marked-alt me-2 text-primary"></i>Location Section</h6>
                    <?= $visToggle('location') ?>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="locationForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_location">

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3"><i class="fas fa-pen me-2 text-primary"></i>Section Content</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Section Label</label>
                                    <input type="text" name="location_subtitle" class="form-control" value="<?= e($locationSection['subtitle'] ?? 'Find Us') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Heading</label>
                                    <input type="text" name="location_title" class="form-control" value="<?= e($locationSection['title'] ?? 'Our Location') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Description</label>
                                    <textarea name="location_description" class="form-control" rows="3"><?= e($locationSection['description'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-3">

                                <h6 class="fw-bold mb-3"><i class="fas fa-address-card me-2 text-primary"></i>Contact Details</h6>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Address</label>
                                    <input type="text" name="location_address" class="form-control" value="<?= e($locationSection['address'] ?? '') ?>" placeholder="123 Medical Center Drive, New York, NY">
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Phone</label>
                                        <input type="text" name="location_phone" class="form-control" value="<?= e($locationSection['phone'] ?? '') ?>" placeholder="+1 (800) 123-4567">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Email</label>
                                        <input type="email" name="location_email" class="form-control" value="<?= e($locationSection['email'] ?? '') ?>" placeholder="info@jmedi.com">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Working Hours</label>
                                    <input type="text" name="location_hours" class="form-control" value="<?= e($locationSection['hours'] ?? '') ?>" placeholder="Mon - Sat: 8:00 AM - 7:00 PM">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3"><i class="fas fa-map me-2 text-primary"></i>Google Map</h6>
                                <div class="alert alert-info small mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>How to get the map URL:</strong>
                                    <ol class="mb-0 mt-2 ps-3">
                                        <li>Open <a href="https://www.google.com/maps" target="_blank" class="fw-bold">Google Maps</a></li>
                                        <li>Search for your location</li>
                                        <li>Click <strong>Share</strong> &rarr; <strong>Embed a map</strong></li>
                                        <li>Copy the entire <code>&lt;iframe&gt;</code> code or just the <code>src</code> URL</li>
                                        <li>Paste it below — both formats work</li>
                                    </ol>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Map Embed URL or iframe Code</label>
                                    <textarea name="location_map_embed" class="form-control" rows="4" placeholder="Paste Google Maps embed URL or full iframe code here..."><?= e($locationSection['map_embed'] ?? '') ?></textarea>
                                </div>

                                <h6 class="fw-bold mb-2"><i class="fas fa-eye me-2 text-primary"></i>Map Preview</h6>
                                <div class="border rounded-3 overflow-hidden" style="height:280px;background:#e8f0fe;" id="mapPreviewContainer">
                                    <?php if (!empty($locationSection['map_embed'])): ?>
                                    <iframe src="<?= e($locationSection['map_embed']) ?>" width="100%" height="280" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                                    <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                        <div class="text-center">
                                            <i class="fas fa-map-marked-alt" style="font-size:3rem;opacity:0.2;"></i>
                                            <p class="mt-2 mb-0 small">Map preview will appear here</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Location Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="departmentsTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-hospital me-2 text-primary"></i>Departments</h6>
                    <?= $visToggle('departments') ?>
                </div>
                <div class="card-body p-5 text-center">
                    <div class="mb-3"><i class="fas fa-hospital" style="font-size:3rem;color:#0dcaf0;opacity:0.4;"></i></div>
                    <h5>Departments</h5>
                    <p class="text-muted mb-3">Departments are managed from their own admin page. Changes there will automatically reflect on the homepage.</p>
                    <a href="/admin/departments.php" class="btn btn-primary px-4"><i class="fas fa-external-link-alt me-2"></i>Manage Departments</a>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="doctorsTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-user-md me-2 text-primary"></i>Doctors</h6>
                    <?= $visToggle('doctors') ?>
                </div>
                <div class="card-body p-5 text-center">
                    <div class="mb-3"><i class="fas fa-user-md" style="font-size:3rem;color:#198754;opacity:0.4;"></i></div>
                    <h5>Doctors</h5>
                    <p class="text-muted mb-3">Doctors are managed from their own admin page. The homepage displays the latest doctors automatically.</p>
                    <a href="/admin/doctors.php" class="btn btn-primary px-4"><i class="fas fa-external-link-alt me-2"></i>Manage Doctors</a>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="ctaCheckupTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-stethoscope me-2 text-primary"></i>CTA - Need a Check-up</h6>
                    <?= $visToggle('cta_checkup') ?>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">The call-to-action banner that appears between Doctors and Appointment sections.</p>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_cta_checkup">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Heading</label>
                                <input type="text" name="cta_heading" class="form-control" value="<?= e($ctaCheckup['heading'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Subtitle Text</label>
                                <input type="text" name="cta_subtitle" class="form-control" value="<?= e($ctaCheckup['subtitle'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button Text</label>
                                <input type="text" name="cta_button_text" class="form-control" value="<?= e($ctaCheckup['button_text'] ?? 'Make Appointment') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button Link</label>
                                <input type="text" name="cta_button_link" class="form-control" value="<?= e($ctaCheckup['button_link'] ?? '/public/appointment.php') ?>">
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save CTA Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="appointmentTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Appointment Section</h6>
                    <?= $visToggle('appointment') ?>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">The left-side content of the appointment booking section. The form itself is auto-generated.</p>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_appointment">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Badge Text</label>
                                <input type="text" name="appt_badge" class="form-control" value="<?= e($appointmentSection['badge_text'] ?? 'Appointment') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Heading (HTML allowed for line breaks)</label>
                                <input type="text" name="appt_heading" class="form-control" value="<?= e($appointmentSection['heading'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="appt_description" class="form-control" rows="3"><?= e($appointmentSection['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Appointment Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="processTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2 text-primary"></i>Working Process Steps</h6>
                        <?= $visToggle('process') ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProcessStep()"><i class="fas fa-plus me-1"></i>Add Step</button>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="processForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_process">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Section Label</label>
                                <input type="text" name="process_subtitle" class="form-control" value="<?= e($processSection['subtitle'] ?? 'Working Process') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Heading</label>
                                <input type="text" name="process_heading" class="form-control" value="<?= e($processSection['heading'] ?? '') ?>">
                            </div>
                        </div>
                        <div id="processSteps">
                            <?php foreach (($processSection['steps'] ?? []) as $pi => $step): ?>
                            <div class="process-step-row border rounded-3 p-3 mb-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><span class="badge bg-primary me-2"><?= str_pad($pi + 1, 2, '0', STR_PAD_LEFT) ?></span>Step <?= $pi + 1 ?></h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.process-step-row').remove()"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Icon</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas <?= e($step['icon']) ?>"></i></span>
                                            <input type="text" name="process_icon[]" class="form-control" value="<?= e($step['icon']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Title</label>
                                        <input type="text" name="process_title[]" class="form-control" value="<?= e($step['title']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Description</label>
                                        <input type="text" name="process_desc[]" class="form-control" value="<?= e($step['description']) ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Process Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="statsTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Statistics Counters</h6>
                        <?= $visToggle('stats') ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStatItem()"><i class="fas fa-plus me-1"></i>Add Item</button>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">Set "auto" as the number to auto-count from the database (e.g., doctors count).</p>
                    <form method="POST" id="statsForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_stats">
                        <div id="statItems">
                            <?php foreach (($statsSection['items'] ?? []) as $si => $stat): ?>
                            <div class="stat-item-row border rounded-3 p-3 mb-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><span class="badge bg-danger me-2"><?= $si + 1 ?></span>Stat #<?= $si + 1 ?></h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.stat-item-row').remove()"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Icon</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas <?= e($stat['icon']) ?>"></i></span>
                                            <input type="text" name="stat_icon[]" class="form-control" value="<?= e($stat['icon']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Number</label>
                                        <input type="text" name="stat_number[]" class="form-control" value="<?= e($stat['number']) ?>" placeholder="25 or auto">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold">Suffix</label>
                                        <input type="text" name="stat_suffix[]" class="form-control" value="<?= e($stat['suffix'] ?? '') ?>" placeholder="+ or %">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Label</label>
                                        <input type="text" name="stat_label[]" class="form-control" value="<?= e($stat['label']) ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Statistics</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="testimonialsTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-comments me-2 text-primary"></i>Testimonials</h6>
                    <?= $visToggle('testimonials') ?>
                </div>
                <div class="card-body p-5 text-center">
                    <div class="mb-3"><i class="fas fa-comments" style="font-size:3rem;color:#ffc107;opacity:0.4;"></i></div>
                    <h5>Testimonials</h5>
                    <p class="text-muted mb-3">Testimonials are managed from their own admin page. The homepage displays them in a carousel automatically.</p>
                    <a href="/admin/testimonials.php" class="btn btn-primary px-4"><i class="fas fa-external-link-alt me-2"></i>Manage Testimonials</a>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="blogTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-newspaper me-2 text-primary"></i>Latest News / Blog</h6>
                    <?= $visToggle('blog') ?>
                </div>
                <div class="card-body p-5 text-center">
                    <div class="mb-3"><i class="fas fa-newspaper" style="font-size:3rem;color:#20c997;opacity:0.4;"></i></div>
                    <h5>Latest News / Blog</h5>
                    <p class="text-muted mb-3">Blog posts are managed from the Blog Posts page. The homepage shows the 3 most recent published posts.</p>
                    <a href="/admin/blog-posts.php" class="btn btn-primary px-4"><i class="fas fa-external-link-alt me-2"></i>Manage Blog Posts</a>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="ctaReadyTab">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-rocket me-2 text-primary"></i>CTA - Ready to Get Started</h6>
                    <?= $visToggle('cta_ready') ?>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">The green call-to-action banner at the bottom of the homepage.</p>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_cta_ready">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Heading</label>
                                <input type="text" name="ctar_heading" class="form-control" value="<?= e($ctaReady['heading'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="ctar_description" class="form-control" rows="3"><?= e($ctaReady['description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button 1 Text</label>
                                <input type="text" name="ctar_btn1_text" class="form-control" value="<?= e($ctaReady['button1_text'] ?? 'Book Appointment') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button 1 Link</label>
                                <input type="text" name="ctar_btn1_link" class="form-control" value="<?= e($ctaReady['button1_link'] ?? '/public/appointment.php') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button 2 Text</label>
                                <input type="text" name="ctar_btn2_text" class="form-control" value="<?= e($ctaReady['button2_text'] ?? 'Contact Us') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button 2 Link</label>
                                <input type="text" name="ctar_btn2_link" class="form-control" value="<?= e($ctaReady['button2_link'] ?? '/public/contact.php') ?>">
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save CTA Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══════════════════ OUR VIDEOS TAB ═══════════════════ -->
        <div class="tab-pane fade" id="videosTab">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fab fa-youtube me-2" style="color:#ff0000;"></i>Our Latest Videos — Section Settings</h6>
                    <?= $visToggle('our_videos') ?>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_video_settings">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Section Title</label>
                                <input type="text" name="vid_title" class="form-control" value="<?= e($videosSection['title'] ?? 'Our Latest Videos') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Subtitle / Tagline</label>
                                <input type="text" name="vid_subtitle" class="form-control" value="<?= e($videosSection['subtitle'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-plus-circle me-2 text-success"></i>Add New Video</h6>
                    <span class="badge bg-secondary"><?= count($videosSection['videos']) ?> video(s)</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_video">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">YouTube URL or Video ID</label>
                                <input type="text" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=xxxxxxxxxx" required>
                                <div class="form-text">Paste full YouTube URL or just the 11-character video ID</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Video Title</label>
                                <input type="text" name="video_title" class="form-control" placeholder="e.g. Cardiology Overview" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success w-100"><i class="fab fa-youtube me-1"></i>Add Video</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($videosSection['videos'])): ?>
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Manage Videos</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <?php foreach ($videosSection['videos'] as $vIdx => $vid): ?>
                        <?php $isActive = $vid['active'] ?? true; ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card border <?= $isActive ? 'border-success' : 'border-secondary' ?> rounded-3 overflow-hidden">
                                <div class="position-relative">
                                    <img src="https://img.youtube.com/vi/<?= e($vid['id']) ?>/hqdefault.jpg"
                                         class="w-100" style="height:140px;object-fit:cover;" alt="<?= e($vid['title']) ?>">
                                    <div class="position-absolute top-0 start-0 m-2">
                                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"><?= $isActive ? 'Active' : 'Hidden' ?></span>
                                    </div>
                                    <div class="position-absolute top-50 start-50 translate-middle">
                                        <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,0,0,0.85);display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-play text-white" style="font-size:1rem;margin-left:3px;"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-2">
                                    <div class="fw-semibold small mb-1 text-truncate" title="<?= e($vid['title']) ?>"><?= e($vid['title']) ?></div>
                                    <div class="text-muted" style="font-size:0.7rem;">ID: <?= e($vid['id']) ?></div>
                                </div>
                                <div class="card-footer p-2 bg-light d-flex gap-2">
                                    <form method="POST" class="d-inline flex-fill">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_video">
                                        <input type="hidden" name="video_index" value="<?= $vIdx ?>">
                                        <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?> w-100">
                                            <i class="fas <?= $isActive ? 'fa-eye-slash' : 'fa-eye' ?>"></i> <?= $isActive ? 'Hide' : 'Show' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this video?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_video">
                                        <input type="hidden" name="video_index" value="<?= $vIdx ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5 text-center">
                    <div class="mb-3"><i class="fab fa-youtube" style="font-size:3rem;color:#ff0000;opacity:0.3;"></i></div>
                    <h6 class="text-muted">No videos added yet</h6>
                    <p class="text-muted small">Use the form above to add your first YouTube video.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <!-- ══════════════════════════════════════════════════════ -->

    </div>

<script>
function addStripItem() {
    const container = document.getElementById('stripItems');
    const count = container.querySelectorAll('.strip-item-row').length + 1;
    const html = `
    <div class="strip-item-row border rounded-3 p-3 mb-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><span class="badge bg-primary me-2">${count}</span>Item #${count}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.strip-item-row').remove()" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Title</label>
                <input type="text" name="strip_title[]" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Subtitle</label>
                <input type="text" name="strip_subtitle[]" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Link URL</label>
                <input type="text" name="strip_link[]" class="form-control" placeholder="/public/page.php">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Icon (FontAwesome class)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-circle"></i></span>
                    <input type="text" name="strip_icon[]" class="form-control" placeholder="fa-calendar-check">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Background Color</label>
                <div class="input-group">
                    <input type="color" name="strip_color[]" class="form-control form-control-color" value="#0D6EFD" style="max-width:60px;">
                    <input type="text" class="form-control" value="#0D6EFD" disabled>
                </div>
            </div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function addFeature() {
    const container = document.getElementById('featuresList');
    const html = `
    <div class="input-group mb-2 feature-row">
        <span class="input-group-text"><i class="fas fa-check text-success"></i></span>
        <input type="text" name="about_features[]" class="form-control" placeholder="Feature text...">
        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.feature-row').remove()"><i class="fas fa-times"></i></button>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function previewAboutImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('aboutImgPreview');
            const placeholder = document.getElementById('aboutImgPlaceholder');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleVisCard(card) {
    const cb = card.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    card.classList.toggle('active', cb.checked);
    card.querySelector('.form-check-label').textContent = cb.checked ? 'Visible' : 'Hidden';
}

async function quickToggleSection(key, checkbox) {
    const label = checkbox.nextElementSibling;
    const fd = new FormData();
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) fd.append('csrf_token', csrfInput.value);
    fd.append('action', 'quick_toggle_section');
    fd.append('section_key', key);
    if (checkbox.checked) fd.append('visible', '1');
    try {
        const res = await fetch('/admin/home-sections.php', { method: 'POST', body: fd });
        if (res.ok) {
            if (label) label.textContent = checkbox.checked ? 'Visible' : 'Hidden';
            const topCard = document.querySelector('.visibility-card input#vis_' + key);
            if (topCard) {
                topCard.checked = checkbox.checked;
                topCard.closest('.visibility-card').classList.toggle('active', checkbox.checked);
                topCard.nextElementSibling.textContent = checkbox.checked ? 'Visible' : 'Hidden';
            }
            const badge = document.querySelector('.card-header .badge.bg-primary');
            if (badge) {
                const total = document.querySelectorAll('.visibility-card input[type="checkbox"]').length;
                const active = document.querySelectorAll('.visibility-card input[type="checkbox"]:checked').length;
                badge.textContent = active + ' / ' + total + ' Active';
            }
        } else {
            checkbox.checked = !checkbox.checked;
        }
    } catch (e) {
        checkbox.checked = !checkbox.checked;
    }
}

function addProcessStep() {
    const container = document.getElementById('processSteps');
    const count = container.querySelectorAll('.process-step-row').length + 1;
    const num = String(count).padStart(2, '0');
    container.insertAdjacentHTML('beforeend', `
    <div class="process-step-row border rounded-3 p-3 mb-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><span class="badge bg-primary me-2">${num}</span>Step ${count}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.process-step-row').remove()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label small fw-semibold">Icon</label><div class="input-group"><span class="input-group-text"><i class="fas fa-circle"></i></span><input type="text" name="process_icon[]" class="form-control" placeholder="fa-star"></div></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Title</label><input type="text" name="process_title[]" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Description</label><input type="text" name="process_desc[]" class="form-control"></div>
        </div>
    </div>`);
}

function addStatItem() {
    const container = document.getElementById('statItems');
    const count = container.querySelectorAll('.stat-item-row').length + 1;
    container.insertAdjacentHTML('beforeend', `
    <div class="stat-item-row border rounded-3 p-3 mb-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><span class="badge bg-danger me-2">${count}</span>Stat #${count}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.stat-item-row').remove()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label small fw-semibold">Icon</label><div class="input-group"><span class="input-group-text"><i class="fas fa-chart-bar"></i></span><input type="text" name="stat_icon[]" class="form-control" placeholder="fa-chart-bar"></div></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Number</label><input type="text" name="stat_number[]" class="form-control" placeholder="25 or auto"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold">Suffix</label><input type="text" name="stat_suffix[]" class="form-control" placeholder="+ or %"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Label</label><input type="text" name="stat_label[]" class="form-control"></div>
        </div>
    </div>`);
}

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab) {
        const tabEl = document.querySelector('[data-bs-target="#' + tab + '"]');
        if (tabEl) {
            const bsTab = new bootstrap.Tab(tabEl);
            bsTab.show();
        }
    }
});
</script>

<style>
.visibility-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 12px 14px;
    cursor: pointer;
    transition: all 0.25s;
    background: #f8f9fa;
}
.visibility-card:hover {
    border-color: #ced4da;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.visibility-card.active {
    border-color: #198754;
    background: #f0fdf4;
}
.visibility-card.active .form-check-label {
    color: #198754;
    font-weight: 600;
}
.visibility-card .form-check-label {
    color: #999;
}
.vis-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.8rem;
    flex-shrink: 0;
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
