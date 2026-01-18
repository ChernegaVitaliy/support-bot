<?php
// Робимо змінну глобальною
global $admin_action_sessions;
$admin_action_sessions = [];

function handleAdminCommand($user_id, $text, $from, $lang, $current_rank, $group_chat_id = null) {
    // Використовуємо group_chat_id для відповіді, якщо він є, інакше - user_id (ЛС)
    $response_chat_id = $group_chat_id ?: $user_id;

    logInfo("ADMIN_CMD від $user_id" . ($group_chat_id ? " в групі $group_chat_id" : " в ЛС") . ": $text rank:" . ($current_rank ?? 'null'));

    if (empty($current_rank)) {
        sendMessage($response_chat_id, t('error_rank_undefined', $lang), null, true);
        logError("Ранг користувача null для $user_id");
        return;
    }

    $parts = explode(' ', $text);
    $command = $parts[0];

    logDebug("ADMIN_PROC $command parts:" . count($parts));

    switch ($command) {
        case "/addadmin":
            if (!hasPermission('admin', $current_rank)) {
                sendMessage($response_chat_id, t('no_permission', $lang), null, true);
                return;
            }
            if (count($parts) === 1) {
                $usage_message = "ℹ️ <b>" . t('usage', $lang) . ":</b> /addadmin [user_id/@username] [rank]\n\n";
                $usage_message .= "📝 <b>" . t('examples', $lang) . ":</b>\n";
                $usage_message .= "• <code>/addadmin 123456789 moderator</code>\n";
                $usage_message .= "• <code>/addadmin @username admin</code>\n\n";
                $usage_message .= "🎯 <b>" . t('available_ranks', $lang) . ":</b> moderator, admin, owner\n\n";
                $usage_message .= "💡 <b>" . t('important', $lang) . ":</b> " . t('user_must_be_in_database', $lang);
                sendMessage($response_chat_id, $usage_message, null, true);
                return;
            }
            handleAddAdmin($response_chat_id, $parts, $lang, $current_rank);
            break;

        case "/removeadmin":
            if (!hasPermission('admin', $current_rank)) {
                sendMessage($response_chat_id, t('no_permission', $lang), null, true);
                return;
            }
            if (count($parts) === 1) {
                $usage_message = "ℹ️ <b>" . t('usage', $lang) . ":</b> /removeadmin [user_id/@username]\n\n";
                $usage_message .= "📝 <b>" . t('examples', $lang) . ":</b>\n";
                $usage_message .= "• <code>/removeadmin 123456789</code>\n";
                $usage_message .= "• <code>/removeadmin @username</code>";
                sendMessage($response_chat_id, $usage_message, null, true);
                return;
            }
            handleRemoveAdmin($response_chat_id, $parts, $lang, $current_rank);
            break;

        case "/setrank":
            if (!hasPermission('admin', $current_rank)) {
                sendMessage($response_chat_id, t('no_permission', $lang), null, true);
                return;
            }
            if (count($parts) === 1) {
                $usage_message = "ℹ️ <b>" . t('usage', $lang) . ":</b> /setrank [user_id/@username] [rank]\n\n";
                $usage_message .= "📝 <b>" . t('examples', $lang) . ":</b>\n";
                $usage_message .= "• <code>/setrank @username admin</code>\n";
                $usage_message .= "• <code>/setrank 123456789 moderator</code>\n\n";
                $usage_message .= "🎯 <b>" . t('available_ranks', $lang) . ":</b> moderator, admin, owner";
                sendMessage($response_chat_id, $usage_message, null, true);
                return;
            }
            handleSetRank($response_chat_id, $parts, $lang, $current_rank);
            break;

        case "/adminlist":
            handleAdminList($response_chat_id, $lang);
            break;

        case "/stats":
            handleAdminStats($response_chat_id, $lang, $current_rank);
            break;

        case "/myrank":
            handleMyRank($response_chat_id, $lang, $current_rank);
            break;

        case "/debug":
            handleDebugToggle($response_chat_id, $current_rank, $lang);
            break;

        // КОМАНДИ ДЛЯ РЕПОРТІВ
        case "/reports":
            handleReportsList($response_chat_id, $current_rank, $lang);
            break;

        case "/report":
            if (count($parts) === 1) {
                handleReportsList($response_chat_id, $current_rank, $lang);
            } else {
                handleReportDetails($response_chat_id, $parts[1], $current_rank, $lang);
            }
            break;

        case "/accept":
            if (!hasPermission('moderator', $current_rank)) {
                sendMessage($response_chat_id, t('no_permission', $lang), null, true);
                return;
            }
            if (count($parts) < 2) {
                $usage_message = "ℹ️ <b>" . t('usage', $lang) . ":</b> /accept [report_id] " . t('comment_placeholder', $lang) . "\n\n";
                $usage_message .= "📝 <b>" . t('examples', $lang) . ":</b>\n";
                $usage_message .= t('accept_examples', $lang) . "\n\n";
                $usage_message .= t('report_id_available', $lang);
                sendMessage($response_chat_id, $usage_message, null, true);
                return;
            }
            handleAcceptReport($response_chat_id, $parts, $current_rank, $user_id, $lang);
            break;

        case "/reject":
            if (!hasPermission('moderator', $current_rank)) {
                sendMessage($response_chat_id, t('no_permission', $lang), null, true);
                return;
            }
            if (count($parts) < 2) {
                $usage_message = "ℹ️ <b>" . t('usage', $lang) . ":</b> /reject [report_id] " . t('reason_placeholder', $lang) . "\n\n";
                $usage_message .= "📝 <b>" . t('examples', $lang) . ":</b>\n";
                $usage_message .= t('reject_examples', $lang) . "\n\n";
                $usage_message .= t('report_id_available', $lang);
                sendMessage($response_chat_id, $usage_message, null, true);
                return;
            }
            handleRejectReport($response_chat_id, $parts, $current_rank, $user_id, $lang);
            break;

        // НОВА КОМАНДА РОЗСИЛКИ
        case "/broadcast":
            if (!hasPermission('owner', $current_rank)) {
                sendMessage($response_chat_id, t('no_permission_owner', $lang), null, true);
                return;
            }
            handleBroadcastCommand($response_chat_id, $parts, $lang, $current_rank);
            break;

        default:
            logWarning("Невідома адмін-команда: $command від $user_id");
            $help_message = "🤖 <b>" . t('admin_commands', $lang) . ":</b>\n\n";
            
            // Команди для модераторів
            if (hasPermission('moderator', $current_rank)) {
                $help_message .= "🛡️ <b>" . t('moderator_permissions', $lang) . ":</b>\n";
                $help_message .= "• <code>/reports</code> - " . t('reports_list_pending', $lang) . "\n";
                $help_message .= "• <code>/report [id]</code> - " . t('view_report', $lang) . "\n";
                $help_message .= "• <code>/accept [id] " . t('comment_placeholder', $lang) . "</code> - " . t('accept_desc', $lang) . "\n";
                $help_message .= "• <code>/reject [id] " . t('reason_placeholder', $lang) . "</code> - " . t('reject_desc', $lang) . "\n\n";
            }
            
            // Команди для адмінів
            if (hasPermission('admin', $current_rank)) {
                $help_message .= "⭐ <b>" . t('admin_permissions', $lang) . ":</b>\n";
                $help_message .= "• <code>/adminlist</code> - " . t('admin_list_desc', $lang) . "\n";
                $help_message .= "• <code>/addadmin [id/@user] [rank]</code> - " . t('addadmin_desc', $lang) . "\n";
                $help_message .= "• <code>/removeadmin [id/@user]</code> - " . t('removeadmin_desc', $lang) . "\n";
                $help_message .= "• <code>/setrank [id/@user] [rank]</code> - " . t('setrank_desc', $lang) . "\n\n";
            }
            
            // Команди для власника
            if (hasPermission('owner', $current_rank)) {
                $help_message .= "👑 <b>" . t('owner_permissions', $lang) . ":</b>\n";
                $help_message .= "• <code>/broadcast</code> - " . t('broadcast_desc', $lang) . "\n";
                $help_message .= "• <code>/debug</code> - " . t('debug_desc', $lang) . "\n";
            }
            
            // Загальні команди
            $help_message .= "📊 <b>" . t('general_commands', $lang) . ":</b>\n";
            $help_message .= "• <code>/stats</code> - " . t('stats_admin', $lang) . "\n";
            $help_message .= "• <code>/myrank</code> - " . t('your_rank', $lang) . "\n\n";
            
            $help_message .= "💡 <i>" . t('to_cancel_process', $lang) . "</i>";
            
            sendMessage($response_chat_id, $help_message, null, true);
            break;
    }
}

// Глобальна змінна для сесій розсилки
$broadcast_sessions = [];

// Стани сесії розсилки
define('BROADCAST_AWAIT_TYPE', 1);
define('BROADCAST_AWAIT_LANGUAGE_SELECTION', 2);
define('BROADCAST_AWAIT_MESSAGE', 3);
define('BROADCAST_AWAIT_MEDIA', 4);
define('BROADCAST_AWAIT_CONFIRM', 5);

// Функція для початку розсилки
function handleBroadcastCommand($chat_id, $parts, $lang, $current_rank) {
    global $broadcast_sessions;

    if (!hasPermission('owner', $current_rank)) {
        sendMessage($chat_id, t('no_permission_owner', $lang), null, true);
        return;
    }

    // Початок нової сесії
    $broadcast_sessions[$chat_id] = [
        'step' => BROADCAST_AWAIT_TYPE,
        'selected_languages' => [],
        'media' => [],
        'message_text' => null,
        'user_lang' => $lang,
        'lang_page' => 0,
        'target_type' => 'all' // all, admins, users
    ];

    $message = "📢 <b>" . t('broadcast_system', $lang) . "</b>\n\n";
    $message .= "🔹 <b>" . t('broadcast_all_users', $lang) . "</b> - " . t('broadcast_all_desc', $lang) . "\n";
    $message .= "🔹 <b>" . t('broadcast_by_language', $lang) . "</b> - " . t('broadcast_lang_desc', $lang) . "\n";
    $message .= "🔹 <b>👑 " . t('broadcast_admins', $lang) . "</b> - " . t('broadcast_admins_desc', $lang) . "\n";
    $message .= "🔹 <b>👤 " . t('broadcast_users', $lang) . "</b> - " . t('broadcast_users_desc', $lang) . "\n\n";
    $message .= "💡 <i>" . t('broadcast_media_hint', $lang) . "</i>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '👥 ' . t('broadcast_all_users', $lang), 'callback_data' => 'broadcast_all'],
                ['text' => '🌍 ' . t('broadcast_by_language', $lang), 'callback_data' => 'broadcast_language']
            ],
            [
                ['text' => '👑 ' . t('broadcast_admins', $lang), 'callback_data' => 'broadcast_admins'],
                ['text' => '👤 ' . t('broadcast_users', $lang), 'callback_data' => 'broadcast_users']
            ],
            [
                ['text' => '❌ ' . t('cancel', $lang), 'callback_data' => 'broadcast_cancel']
            ]
        ]
    ];

    sendMessage($chat_id, $message, $keyboard);
    logInfo("Розсилка розпочата для owner: $chat_id");
}

// Обробка callback від кнопок
function handleBroadcastCallback($callback_data, $chat_id, $message_id, $from, $lang) {
    global $broadcast_sessions;

    $session = $broadcast_sessions[$chat_id] ?? null;
    if (!$session) {
        logWarning("❌ Сесія не знайдена для $chat_id");
        return;
    }

    logInfo("🔔 Отримано: '$callback_data' від $chat_id");

    switch ($callback_data) {
        case 'broadcast_all':
            logInfo("✅ Обрано розсилку всім користувачам");
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MESSAGE;
            $broadcast_sessions[$chat_id]['selected_languages'] = ['all'];
            $broadcast_sessions[$chat_id]['target_type'] = 'all';
            editMessage($chat_id, $message_id,
                "✅ <b>" . t('broadcast_selected_all', $lang) . "</b>\n\n" .
                "📝 " . t('broadcast_send_message', $lang) . "\n\n" .
                "💡 <i>" . t('broadcast_media_album_hint', $lang) . "</i>"
            );
            showMediaControls($chat_id, $session);
            break;

        case 'broadcast_language':
            logInfo("✅ Обрано розсилку по мовах");
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_LANGUAGE_SELECTION;
            $broadcast_sessions[$chat_id]['target_type'] = 'all';
            showLanguageSelection($chat_id, $message_id, $session);
            break;

        case 'broadcast_admins':
            logInfo("✅ Обрано розсилку адмінам");
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_LANGUAGE_SELECTION;
            $broadcast_sessions[$chat_id]['target_type'] = 'admins';
            editMessage($chat_id, $message_id,
                "✅ <b>" . t('broadcast_selected_admins', $lang) . "</b>\n\n" .
                "📝 " . t('broadcast_send_message', $lang) . "\n\n" .
                "💡 <i>" . t('broadcast_media_album_hint', $lang) . "</i>"
            );
            showLanguageSelection($chat_id, $message_id, $session);
            break;

        case 'broadcast_users':
            logInfo("✅ Обрано розсилку користувачам");
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_LANGUAGE_SELECTION;
            $broadcast_sessions[$chat_id]['target_type'] = 'users';
            editMessage($chat_id, $message_id,
                "✅ <b>" . t('broadcast_selected_users', $lang) . "</b>\n\n" .
                "📝 " . t('broadcast_send_message', $lang) . "\n\n" .
                "💡 <i>" . t('broadcast_media_album_hint', $lang) . "</i>"
            );
            showLanguageSelection($chat_id, $message_id, $session);
            break;

        case 'broadcast_cancel':
            logInfo("❌ Скасування розсилки на ранньому етапі");
            unset($broadcast_sessions[$chat_id]);
            editMessage($chat_id, $message_id, "<b>" . t('broadcast_cancelled', $lang) . "</b>");
            break;

        case 'broadcast_add_photo':
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MEDIA;
            $broadcast_sessions[$chat_id]['awaiting_media_type'] = 'photo';
            sendMessage($chat_id, "📸 <b>" . t('add_photo_title', $lang) . "</b>\n\n" . t('broadcast_send_photo', $lang));
            break;

        case 'broadcast_add_video':
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MEDIA;
            $broadcast_sessions[$chat_id]['awaiting_media_type'] = 'video';
            sendMessage($chat_id, "🎬 <b>" . t('add_video_title', $lang) . "</b>\n\n" . t('broadcast_send_video', $lang));
            break;

        case 'broadcast_view_media':
            showMediaGallery($chat_id, $session);
            break;

        case 'broadcast_clear_media':
            $broadcast_sessions[$chat_id]['media'] = [];
            sendMessage($chat_id, "🗑️ <b>" . t('media_cleared', $lang) . "</b>");
            showMediaControls($chat_id, $broadcast_sessions[$chat_id]);
            break;

        case 'broadcast_finish_media':
            // Виходимо з режиму очікування медіа
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MESSAGE;
            unset($broadcast_sessions[$chat_id]['awaiting_media_type']);

            if (empty($broadcast_sessions[$chat_id]['message_text']) && empty($broadcast_sessions[$chat_id]['media'])) {
                sendMessage($chat_id, "<b>" . t('error', $lang) . "</b>\n\n" . t('broadcast_no_content', $lang));
                return;
            }
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_CONFIRM;
            showBroadcastPreview($chat_id, $broadcast_sessions[$chat_id], $lang);
            break;

        case 'broadcast_confirm':
            logInfo("🚀 Підтвердження розсилки");
            executeBroadcast($chat_id, $session, $lang);
            unset($broadcast_sessions[$chat_id]);
            editMessage($chat_id, $message_id, "🚀 <b>" . t('broadcast_started', $lang) . "</b>");
            break;

        case 'broadcast_cancel_final':
            logInfo("❌ Фінальне скасування розсилки");
            unset($broadcast_sessions[$chat_id]);
            editMessage($chat_id, $message_id, "<b>" . t('broadcast_cancelled', $lang) . "</b>");
            break;

        case 'broadcast_edit':
            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MESSAGE;
            sendMessage($chat_id, "✏️ <b>" . t('broadcast_editing', $lang) . "</b>\n\n" . t('broadcast_send_new_message', $lang));
            showMediaControls($chat_id, $broadcast_sessions[$chat_id]);
            break;

        case 'broadcast_lang_next':
            logInfo("➡️ Натиснуто 'Далі' у виборі мов");

            $target_type = $session['target_type'] ?? 'all';
            $target_name = match($target_type) {
                'admins' => t('broadcast_admins_target', $lang),
                'users' => t('broadcast_users_target', $lang),
                default => t('broadcast_all_target', $lang)
            };

            // Якщо не обрано жодної мови - розсилаємо всім
            if (empty($session['selected_languages'])) {
                $broadcast_sessions[$chat_id]['selected_languages'] = ['all'];
                $langs_text = t('broadcast_all_target', $lang) . " $target_name";
            } else {
                $langs_text = implode(', ', array_map('getLanguageName', $session['selected_languages']));
                $langs_text .= " ($target_name)";
            }

            $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MESSAGE;

            editMessage($chat_id, $message_id,
                "✅ <b>" . t('broadcast_selected_for', $lang) . ":</b> $langs_text\n\n" .
                "📝 " . t('broadcast_send_message', $lang)
            );
            showMediaControls($chat_id, $broadcast_sessions[$chat_id]);
            break;

        case 'broadcast_lang_prev':
            logInfo("⬅️ Натиснуто 'Назад' у виборі мов");
            showLanguageSelection($chat_id, $message_id, $session, $callback_data);
            break;

        case 'broadcast_lang_next_page':
            logInfo("📄 Натиснуто 'Далі' для наступної сторінки мов");
            showLanguageSelection($chat_id, $message_id, $session, $callback_data);
            break;

        default:
            if (strpos($callback_data, 'broadcast_lang_') === 0) {
                $selected_lang = substr($callback_data, 15);
                $selected_lang = trim($selected_lang);
                $selected_lang = str_replace('_', '', $selected_lang);
                handleLanguageSelection($chat_id, $message_id, $session, $selected_lang);
            } else {
                logWarning("❌ Невідомий callback: '$callback_data'");
            }
            break;
    }
}

// Функція для показу керування медіа
function showMediaControls($chat_id, $session) {
    $media_count = count($session['media'] ?? []);
    $photos_count = count(array_filter($session['media'] ?? [], function($m) { return $m['type'] === 'photo'; }));
    $videos_count = count(array_filter($session['media'] ?? [], function($m) { return $m['type'] === 'video'; }));

    $lang = $session['user_lang'];

    $message = "📦 <b>" . t('media_management', $lang) . "</b>\n\n";

    if (!empty($session['message_text'])) {
        $message .= "📝 <b>" . t('text', $lang) . ":</b> " . substr($session['message_text'], 0, 100) . (strlen($session['message_text']) > 100 ? "..." : "") . "\n\n";
    }

    $message .= "📊 <b>" . t('media_statistics', $lang) . ":</b>\n";
    $message .= "📸 " . t('photos', $lang) . ": $photos_count " . t('items', $lang) . "\n";
    $message .= "🎬 " . t('videos', $lang) . ": $videos_count " . t('items', $lang) . "\n";
    $message .= "📦 " . t('total', $lang) . ": $media_count " . t('files', $lang) . "\n\n";

    if ($media_count > 0) {
        $message .= "💡 <i>" . t('media_album_hint', $lang) . "</i>\n\n";
    }

    $message .= t('choose_action', $lang);

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📸 ' . t('add_photo', $lang), 'callback_data' => 'broadcast_add_photo'],
                ['text' => '🎬 ' . t('add_video', $lang), 'callback_data' => 'broadcast_add_video']
            ]
        ]
    ];

    // Додаємо кнопки перегляду та очищення якщо є медіа
    if ($media_count > 0) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '👀 ' . t('view_media', $lang), 'callback_data' => 'broadcast_view_media'],
            ['text' => '🗑️ ' . t('clear_all', $lang), 'callback_data' => 'broadcast_clear_media']
        ];
    }

    // Кнопка завершення
    $keyboard['inline_keyboard'][] = [
        ['text' => '✅ ' . t('finish_adding', $lang), 'callback_data' => 'broadcast_finish_media']
    ];

    sendMessage($chat_id, $message, $keyboard);
}

// Функція для перегляду галереї медіа
function showMediaGallery($chat_id, $session) {
    $media = $session['media'] ?? [];
    $lang = $session['user_lang'];

    if (empty($media)) {
        sendMessage($chat_id, "📭 <b>" . t('no_media_added', $lang) . "</b>");
        return;
    }

    // Спочатку відправляємо медіа-альбом
    if (!empty($media)) {
        $album_caption = "🖼️ <b>" . t('preview_media_files', $lang) . "</b>\n\n";
        $album_caption .= "📦 " . t('total_files_album', $lang, [count($media)]);

        // Відправляємо альбом
        sendMediaGroup($chat_id, $media, $album_caption);
        logInfo("📦 Відправлено попередній перегляд альбому з " . count($media) . " файлів");
    }

    // Потім відправляємо детальну інформацію
    $message = "📋 <b>" . t('media_details', $lang) . ":</b>\n\n";

    foreach ($media as $index => $item) {
        $number = $index + 1;
        $type_emoji = $item['type'] === 'photo' ? '📸' : ' 🎬';
        $type_text = $item['type'] === 'photo' ? t('photo', $lang) : t('video', $lang);
        $message .= "{$type_emoji} <b>" . t('media', $lang) . " {$number}</b> - {$type_text}\n";

        if (!empty($item['caption'])) {
            $message .= "   📝 <b>" . t('caption', $lang) . ":</b> " . substr($item['caption'], 0, 100) . (strlen($item['caption']) > 100 ? "..." : "") . "\n";
        }

        // Показуємо розмір файлу якщо є
        if (!empty($item['file_size'])) {
            $file_size_mb = round($item['file_size'] / 1024 / 1024, 2);
            $message .= "   💾 <b>" . t('file_size', $lang) . ":</b> {$file_size_mb} MB\n";
        }

        $message .= "   🆔 <b>ID:</b> <code>" . substr($item['file_id'], 0, 20) . "...</code>\n\n";
    }

    $message .= "💡 <i>" . t('media_will_be_sent_as_album', $lang) . "</i>";

    sendMessage($chat_id, $message);
}

// Обробка вибору мови
function handleLanguageSelection($chat_id, $message_id, $session, $selected_lang) {
    global $broadcast_sessions;

    $selected_langs = $session['selected_languages'] ?? [];

    if (in_array($selected_lang, $selected_langs)) {
        $selected_langs = array_diff($selected_langs, [$selected_lang]);
    } else {
        $selected_langs[] = $selected_lang;
    }

    $broadcast_sessions[$chat_id]['selected_languages'] = $selected_langs;
    showLanguageSelection($chat_id, $message_id, $broadcast_sessions[$chat_id]);
}

// Функція для отримання назви мови
function getLanguageName($lang_code) {
    $language_names = [
        'uk' => '🇺🇦 Українська',
        'ru' => '🇷🇺 Русский',
        'en' => '🇺🇸 English',
        'es' => '🇪🇸 Español',
        'de' => '🇩🇪 Deutsch',
        'fr' => '🇫🇷 Français',
        'it' => '🇮🇹 Italiano',
        'pt' => '🇵🇹 Português',
        'zh' => '🇨🇳 中文',
        'ja' => '🇯🇵 日本語',
        'ko' => '🇰🇷 한국어',
        'ar' => '🇸🇦 العربية',
        'fa' => '🇮🇷 فارسی',
        'tr' => '🇹🇷 Türkçe',
        'pl' => '🇵🇱 Polski',
        'nl' => '🇳🇱 Nederlands',
        'cs' => '🇨🇿 Čeština',
        'sr' => '🇷🇸 Српски',
        'bg' => '🇧🇬 Български',
        'ro' => '🇷🇴 Română',
        'hu' => '🇭🇺 Magyar',
        'fi' => '🇫🇮 Suomi',
        'sv' => '🇸🇪 Svenska',
        'da' => '🇩🇰 Dansk',
        'nb' => '🇳🇴 Norsk',
        'hi' => '🇮🇳 हिन्दी',
        'id' => '🇮🇩 Indonesia',
        'vi' => '🇻🇳 Tiếng Việt',
        'th' => '🇹🇭 ไทย',
        'el' => '🇬🇷 Ελληνικά',
        'he' => '🇮🇱 עברית',
        'hr' => '🇭🇷 Hrvatski',
        'sk' => '🇸🇰 Slovenčina',
        'uz' => '🇺🇿 Oʻzbekcha',
        'ms' => '🇲🇾 Bahasa Melayu',
        'kk' => '🇰🇿 Қазақша',
        'ca' => '🇪🇸 Català',
        'be' => '🇧🇾 Беларуская'
    ];

    return $language_names[$lang_code] ?? $lang_code;
}

// Функція для показу вибору мов
function showLanguageSelection($chat_id, $message_id, $session, $action = 'broadcast_language') {
    global $broadcast_sessions;

    $all_languages = ['uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja', 'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro', 'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el', 'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'];
    $lang = $session['user_lang'];
    $target_type = $session['target_type'] ?? 'all';

    // Визначаємо поточну сторінку
    $current_page = $session['lang_page'] ?? 0;
    $languages_per_page = 8;

    if ($action === 'broadcast_lang_next_page') {
        $current_page++;
    } elseif ($action === 'broadcast_lang_prev') {
        $current_page = max(0, $current_page - 1);
    }

    $broadcast_sessions[$chat_id]['lang_page'] = $current_page;

    $total_pages = ceil(count($all_languages) / $languages_per_page);
    $start_index = $current_page * $languages_per_page;
    $page_languages = array_slice($all_languages, $start_index, $languages_per_page);

    // Заголовок в залежності від типу розсилки
    $target_name = match($target_type) {
        'admins' => t('broadcast_admins_target', $lang),
        'users' => t('broadcast_users_target', $lang),
        default => t('broadcast_all_target', $lang)
    };

    $message = "🌍 <b>" . t('select_languages_broadcast', $lang) . " $target_name:</b>\n\n";

    // Показуємо вибрані мови
    $selected_langs = $session['selected_languages'] ?? [];
    if (!empty($selected_langs)) {
        $selected_text = implode(', ', array_map('getLanguageName', $selected_langs));
        $message .= "✅ <b>" . t('selected', $lang) . ":</b> $selected_text\n\n";
    }

    $message .= "📋 <i>" . t('page', $lang) . " " . ($current_page + 1) . " " . t('of', $lang) . " $total_pages</i>\n";
    $message .= "💡 <i>" . t('select_multiple_languages', $lang) . "</i>\n\n";
    $message .= "🔘 <b>" . t('broadcast_or_send_all', $lang) . " $target_name</b>";

    // Створюємо клавіатуру з мовами
    $keyboard = ['inline_keyboard' => []];

    // Додаємо мови по 2 в рядок
    $row = [];
    foreach ($page_languages as $lang_code) {
        $is_selected = in_array($lang_code, $selected_langs);
        $emoji = $is_selected ? '✅ ' : '';
        $button_text = $emoji . getLanguageName($lang_code);

        $row[] = ['text' => $button_text, 'callback_data' => 'broadcast_lang_' . $lang_code];

        if (count($row) == 2) {
            $keyboard['inline_keyboard'][] = $row;
            $row = [];
        }
    }

    // Додаємо останній рядок якщо потрібно
    if (!empty($row)) {
        $keyboard['inline_keyboard'][] = $row;
    }

    // Додаємо кнопки навігації
    $nav_buttons = [];
    if ($current_page > 0) {
        $nav_buttons[] = ['text' => '⬅️ ' . t('back', $lang), 'callback_data' => 'broadcast_lang_prev'];
    }

    $nav_buttons[] = ['text' => '✅ ' . t('next', $lang), 'callback_data' => 'broadcast_lang_next'];

    if ($current_page < $total_pages - 1) {
        $nav_buttons[] = ['text' => t('next', $lang) . ' ➡️', 'callback_data' => 'broadcast_lang_next_page'];
    }

    $keyboard['inline_keyboard'][] = $nav_buttons;

    // Кнопка скасування
    $keyboard['inline_keyboard'][] = [['text' => '❌ ' . t('cancel', $lang), 'callback_data' => 'broadcast_cancel']];

    editMessage($chat_id, $message_id, $message, $keyboard);
}

// Функція для обробки сесій розсилки
function handleBroadcastSession($chat_id, $message, $from, $lang) {
    global $broadcast_sessions;

    $session = $broadcast_sessions[$chat_id] ?? null;
    if (!$session) return false;

    // 🔥 ДОДАЄМО ПЕРЕВІРКУ /cancel
    if (isset($message['text']) && $message['text'] === '/cancel') {
        unset($broadcast_sessions[$chat_id]);
        sendMessage($chat_id, "❌ " . t('broadcast_cancelled', $lang));
        return true;
    }

    switch ($session['step']) {
        case BROADCAST_AWAIT_MESSAGE:
            return processBroadcastMessage($chat_id, $message, $session, $lang);

        case BROADCAST_AWAIT_MEDIA:
            return processBroadcastMedia($chat_id, $message, $session, $lang);

        case BROADCAST_AWAIT_CONFIRM:
            return processBroadcastConfirmation($chat_id, $message, $session, $lang);
    }

    return true;
}

// Обробка текстового повідомлення
function processBroadcastMessage($chat_id, $message, $session, $lang) {
    global $broadcast_sessions;

    if (isset($message['text'])) {
        $broadcast_sessions[$chat_id]['message_text'] = $message['text'];
        logInfo("📝 Отримано текст для розсилки: " . substr($message['text'], 0, 50) . "...");

        sendMessage($chat_id, "✅ <b>" . t('text_saved', $lang) . "</b>");
        showMediaControls($chat_id, $broadcast_sessions[$chat_id]);
        return true;
    }

    // Якщо відправлено медіа без тексту - пропонуємо додати текст
    if (isset($message['photo']) || isset($message['video'])) {
        sendMessage($chat_id,  t('broadcast_send_text_first', $lang));
        return true;
    }

    return false;
}

// Обробка додавання медіа
function processBroadcastMedia($chat_id, $message, $session, $lang) {
    global $broadcast_sessions;

    $media_item = [];
    $awaiting_type = $broadcast_sessions[$chat_id]['awaiting_media_type'] ?? '';

    if (isset($message['photo']) && $awaiting_type === 'photo') {
        $photo = end($message['photo']);
        $media_item = [
            'type' => 'photo',
            'file_id' => $photo['file_id'],
            'caption' => $message['caption'] ?? '',
            'file_size' => $photo['file_size'] ?? null,
            'added_at' => date('Y-m-d H:i:s')
        ];
        logInfo("🖼️ Додано фото до розсилки, file_id: " . substr($photo['file_id'], 0, 20) . "...");

    } elseif (isset($message['video']) && $awaiting_type === 'video') {
        $video = $message['video'];
        $media_item = [
            'type' => 'video',
            'file_id' => $video['file_id'],
            'caption' => $message['caption'] ?? '',
            'duration' => $video['duration'] ?? null,
            'file_size' => $video['file_size'] ?? null,
            'added_at' => date('Y-m-d H:i:s')
        ];
        logInfo("🎬 Додано відео до розсилки, file_id: " . substr($video['file_id'], 0, 20) . "...");

    } else {
        // Якщо це не той тип медіа, що очікуємо - пропонуємо використати кнопки
        sendMessage($chat_id,
            "❌ " . t('wrong_media_type', $lang) . "\n\n" .
            "💡 " . t('use_correct_media_button', $lang)
        );
        showMediaControls($chat_id, $broadcast_sessions[$chat_id]);
        return true;
    }

    // Додаємо медіа до сесії
    if (!isset($broadcast_sessions[$chat_id]['media'])) {
        $broadcast_sessions[$chat_id]['media'] = [];
    }

    $broadcast_sessions[$chat_id]['media'][] = $media_item;

    // ⭐⭐⭐ НЕ ВИХОДИМО З РЕЖИМУ ОЧІКУВАННЯ МЕДІА ⭐⭐⭐
    // $broadcast_sessions[$chat_id]['step'] = BROADCAST_AWAIT_MESSAGE;
    // unset($broadcast_sessions[$chat_id]['awaiting_media_type']);

    $media_count = count($broadcast_sessions[$chat_id]['media']);
    $media_type = $media_item['type'] === 'photo' ? t('photo', $lang) : t('video', $lang);

    // Повідомляємо про успішне додавання та залишаємо в режимі додавання медіа
    sendMessage($chat_id,
        "✅ <b>{$media_type} " . t('successfully_added', $lang) . "!</b>\n\n" .
        "📊 " . t('total_media_count', $lang, [$media_count]) . "\n\n" .
        "💡 " . t('add_more_media_or_finish', $lang)
    );

    // Показуємо кнопки керування медіа знову
    showMediaControls($chat_id, $broadcast_sessions[$chat_id]);

    return true;
}

// Показ попереднього перегляду з кнопками підтвердження
function showBroadcastPreview($chat_id, $session, $lang) {
    $preview_message = "👁️ <b>" . t('broadcast_preview', $lang) . "</b>\n\n";

    // Інформація про отримувачів
    if (in_array('all', $session['selected_languages'])) {
        $preview_message .= "👥 <b>" . t('recipients', $lang) . ":</b> " . t('all_users', $lang) . "\n";
    } else {
        $langs_text = implode(', ', array_map('getLanguageName', $session['selected_languages']));
        $preview_message .= "🌍 <b>" . t('languages', $lang) . ":</b> $langs_text\n";
    }

    // Інформація про контент
    $preview_message .= "📝 <b>" . t('text', $lang) . ":</b> " . ($session['message_text'] ?: t('none', $lang)) . "\n";

    $media_count = count($session['media'] ?? []);
    $photos_count = count(array_filter($session['media'] ?? [], function($m) { return $m['type'] === 'photo'; }));
    $videos_count = count(array_filter($session['media'] ?? [], function($m) { return $m['type'] === 'video'; }));

    $preview_message .= "📦 <b>" . t('media', $lang) . ":</b> $media_count " . t('files', $lang) . " ($photos_count " . t('photos', $lang) . ", $videos_count " . t('videos', $lang) . ")\n\n";
    $preview_message .= "💡 <i>" . t('media_album_hint', $lang) . "</i>\n\n";

    $preview_message .= "❓ <b>" . t('everything_correct_confirm', $lang) . "</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ ' . t('yes_start_broadcast', $lang), 'callback_data' => 'broadcast_confirm'],
                ['text' => '✏️ ' . t('edit', $lang), 'callback_data' => 'broadcast_edit']
            ],
            [
                ['text' => '❌ ' . t('cancel', $lang), 'callback_data' => 'broadcast_cancel_final']
            ]
        ]
    ];

    // Відправляємо попередній перегляд контенту
    if (!empty($session['media'])) {
        sendMediaGroup($chat_id, $session['media'], $session['message_text']);
        logInfo("📦 Відправлено попередній перегляд альбому з " . count($session['media']) . " файлів");
    } elseif (!empty($session['message_text'])) {
        sendMessage($chat_id, $session['message_text'], null, true);
    }

    // Інструкція з кнопками
    sendMessage($chat_id, $preview_message, $keyboard);
}

// Обробка фінального підтвердження
function processBroadcastConfirmation($chat_id, $message, $session, $lang) {
    global $broadcast_sessions;

    $text = strtolower(trim($message['text'] ?? ''));

    if (in_array($text, ['так', 'yes', 'y', 'ок', 'ok', 'підтверджую', 'confirm'])) {
        sendMessage($chat_id, "🚀 <b>" . t('broadcast_starting', $lang) . "</b>");
        executeBroadcast($chat_id, $session, $lang);
        unset($broadcast_sessions[$chat_id]);
    } else {
        sendMessage($chat_id, "❌ <b>" . t('broadcast_cancelled', $lang) . "</b>");
        unset($broadcast_sessions[$chat_id]);
    }

    return true;
}

// Виконання розсилки
function executeBroadcast($chat_id, $session, $lang) {
    global $db;

    logInfo("🔍 СТАРТ РОЗСИЛКИ");
    logInfo("🎯 Тип розсилки: " . ($session['target_type'] ?? 'all'));
    logInfo("🌍 Мови: " . implode(', ', $session['selected_languages']));
    logInfo("💬 Текст: " . ($session['message_text'] ?? 'NULL'));
    logInfo("📦 Медіа: " . count($session['media'] ?? []));

    // Отримуємо користувачів в залежності від типу розсилки
    $users = [];
    $all_users = $db->getAllUsers();

    $target_type = $session['target_type'] ?? 'all';

    foreach ($all_users as $user) {
        $user_id = $user['chat_id'];
        $is_admin = $db->isAdmin($user_id);
        $user_lang = $db->getUserLanguage($user_id) ?? 'uk';

        // Перевірка типу користувача
        $user_matches_type = false;
        if ($target_type === 'admins' && $is_admin) {
            $user_matches_type = true;
        } elseif ($target_type === 'users' && !$is_admin) {
            $user_matches_type = true;
        } elseif ($target_type === 'all') {
            $user_matches_type = true;
        }

        if (!$user_matches_type) {
            continue; // Пропускаємо якщо тип не підходить
        }

        // Перевірка мови
        $user_matches_language = false;
        if (in_array('all', $session['selected_languages'])) {
            $user_matches_language = true;
        } else {
            $user_matches_language = in_array($user_lang, $session['selected_languages']);
        }

        if (!$user_matches_language) {
            continue; // Пропускаємо якщо мова не підходить
        }

        // Додаємо користувача тільки якщо відповідає обом фільтрам
        $users[] = $user;
    }

    $total_users = count($users);

    if ($total_users === 0) {
        $target_name = match($target_type) {
            'admins' => t('broadcast_no_admins', $lang),
            'users' => t('broadcast_no_users', $lang),
            default => t('broadcast_no_users', $lang)
        };
        sendMessage($chat_id, "❌ <b>" . t('broadcast_error', $lang) . "</b>\n\n" . $target_name, null, true);
        return;
    }

    $target_name = match($target_type) {
        'admins' => t('broadcast_admins_target', $lang),
        'users' => t('broadcast_users_target', $lang),
        default => t('broadcast_all_target', $lang)
    };

    logInfo("🚀 Початок розсилки для $total_users $target_name...");
    sendMessage($chat_id, "📊 <b>" . t('broadcast_progress', $lang) . " $target_name</b>\n\n" . t('progress', $lang) . ": 0/$total_users\n✅ " . t('successful', $lang) . ": 0", null, true);

    $success = 0;
    $failed = 0;
    $current = 0;

    foreach ($users as $user) {
        $current++;
        $user_chat_id = $user['chat_id'];

        try {
            // Відправляємо контент
            if (!empty($session['media'])) {
                $result = sendMediaGroup($user_chat_id, $session['media'], $session['message_text']);
                if (!$result) {
                    throw new Exception(t('album_send_error', $lang));
                }
            } else {
                sendMessage($user_chat_id, $session['message_text'], null, true);
            }

            $success++;
            logDebug("✅ " . t('successfully_sent_to', $lang) . " $user_chat_id");

        } catch (Exception $e) {
            $failed++;
            logError("❌ " . t('send_error_for', $lang) . " $user_chat_id: " . $e->getMessage());
        }

        // Оновлення прогресу кожні 5 користувачів
        if ($current % 5 === 0 || $current === $total_users) {
            $progress_message = "📊 <b>" . t('broadcast_progress', $lang) . " $target_name</b>\n\n" . t('progress', $lang) . ": $current/$total_users\n✅ " . t('successful', $lang) . ": $success\n❌ " . t('errors', $lang) . ": $failed";
            sendMessage($chat_id, $progress_message, null, true, 'HTML');
            logInfo("📈 " . t('progress_log', $lang) . ": $current/$total_users, " . t('successful', $lang) . ": $success, " . t('errors', $lang) . ": $failed");
        }

        usleep(150000); // Затримка між користувачами
    }

    $final_message = "🎉 <b>" . t('broadcast_completed', $lang) . " $target_name!</b>\n\n✅ " . t('successful', $lang) . ": $success\n❌ " . t('errors', $lang) . ": $failed\n👥 " . t('total', $lang) . ": $total_users";
    sendMessage($chat_id, $final_message, null, true, 'HTML');

    logInfo("🏁 " . t('broadcast_finished_log', $lang) . ": " . t('successful', $lang) . " $success, " . t('errors', $lang) . " $failed, " . t('total', $lang) . " $total_users");
}

// Функція для перевірки чи користувач в сесії розсилки
function isInBroadcastSession($chat_id) {
    global $broadcast_sessions;
    return isset($broadcast_sessions[$chat_id]);
}

// Функція для обробки callback повідомлень розсилки
function handleBroadcastCallbackMessage($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];       
    $message_id = $callback_query['message']['message_id'];
    $callback_data = $callback_query['data'];                  
    $from = $callback_query['from'];
    $user_lang = detectLanguage($from);
    handleBroadcastCallback($callback_data, $chat_id, $message_id, $from, $user_lang);                                
}

// Функція для редагування повідомлення
function editMessage($chat_id, $message_id, $text, $reply_markup = null) {                                                
    global $apiURL;

    $data = [                                                      
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];                                                     
    if ($reply_markup) {                                           
        $data['reply_markup'] = json_encode($reply_markup);
    }                                                                                                                     
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . "editMessageText",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $result = curl_exec($ch);                                  
    curl_close($ch);                                       
    return $result;
}

// Список репортів
function handleReportsList($response_chat_id, $current_rank, $lang) {
    global $db;

    if (!hasPermission('moderator', $current_rank)) {              
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);                                                
        return;
    }

    $reports = $db->getAllReports('pending');

    if (empty($reports)) {                                         
        sendMessage($response_chat_id, t('no_reports', $lang), null, true);                                                   
        return;
    }
                                                               
    $message = "🚨 <b>" . t('reports_list_pending', $lang) . ":</b>\n\n";
                                                               
    foreach ($reports as $report) {
        $status_emoji = "⏳";
        $message .= "{$status_emoji} <b>" . t('report_id', $lang, [$report['id']]) . "</b>\n";                                
        $message .= "👤 " . t('reporter', $lang) . ": <code>{$report['reporter_nick']}</code>\n";                             
        $message .= "🚫 " . t('violator', $lang) . ": <code>{$report['reported_nick']}</code>\n";
        $message .= "📋 " . t('reason', $lang) . ": <code>{$report['reason']}</code>\n";
        $message .= "🕐 " . t('created_at', $lang) . ": " . date("d.m.Y H:i", strtotime($report['created_at'])) . "\n";
                                                                   
        // Перевіряємо тип доказів
        if ($report['proof_type'] === 'photo') {
            $message .= "📷 " . t('has_photo_proof', $lang) . "\n";
        } elseif ($report['proof_type'] === 'video') {
            $message .= "🎬 " . t('has_video_proof', $lang) . "\n";                                                           
        } elseif (!empty($report['proof'])) {
            $message .= "📝 " . t('has_text_proof', $lang) . "\n";
        }
                                                                   
        $message .= t('view_report_command', $lang, [$report['id']]) . "\n\n";                                            
    }                                                      
    
    $message .= t('use_report_id', $lang);                 
    sendMessage($response_chat_id, $message);
}

// Деталі репорту з медіа та кнопками
function handleReportDetails($response_chat_id, $report_id, $current_rank, $lang) {                                       
    global $db;

    logInfo("🔍 handleReportDetails: chat_id=$response_chat_id, report_id=$report_id, мова: $lang");
                                                               
    if (!hasPermission('moderator', $current_rank)) {
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);
        return;
    }

    $report = $db->getReportById($report_id);              
    if (!$report) {                                                
        sendMessage($response_chat_id, t('report_not_found', $lang, [$report_id]), null, true);
        return;                                                
    }

    // Створюємо детальний опис
    $status_emoji = match($report['status']) {
        'pending' => '⏳',                                         
        'accepted' => '✅',
        'rejected' => '❌',
        default => '🔹'
    };                                                     
    
    $caption = "{$status_emoji} <b>" . t('report_id', $lang, [$report['id']]) . "</b>\n\n";
    $caption .= "👤 <b>" . t('reporter', $lang) . ":</b> <code>{$report['reporter_nick']}</code>\n";
    $caption .= "🚫 <b>" . t('violator', $lang) . ":</b> <code>{$report['reported_nick']}</code>\n";
    $caption .= "📋 <b>" . t('reason', $lang) . ":</b> <code>{$report['reason']}</code>\n";
                                                               
    if (!empty($report['proof']) && $report['proof_type'] === 'text') {
        $caption .= "📝 <b>" . t('proof', $lang) . ":</b> {$report['proof']}\n";
    }                                                      
    
    $caption .= "🕐 <b>" . t('created_at', $lang) . ":</b> " . date("d.m.Y H:i", strtotime($report['created_at'])) . "\n";
    $caption .= "📊 <b>" . t('status', $lang) . ":</b> " . getStatusText($report['status'], $lang) . "\n";
                                                               
    if (!empty($report['admin_notes'])) {
        $caption .= "💬 <b>" . t('admin_comment', $lang) . ":</b> {$report['admin_notes']}\n";
    }
                                                               
    // Створюємо кнопки
    $keyboard = null;                                          
    if ($report['status'] === 'pending') {
        // Спочатку відправляємо повідомлення з інфою та отримуємо його message_id                                            
        $report_message_id = null;

        if ($report['proof_type'] === 'multiple_media' && !empty($report['file_id'])) {
            $media_files = json_decode($report['file_id'], true);
            if (is_array($media_files) && count($media_files) > 0) {
                // Для альбому не можемо отримати message_id, тому передаємо 0                                                        
                sendMediaGroup($response_chat_id, $media_files, $caption);                                                            
                $report_message_id = 0; // Позначка що це альбом
            }
        }
        elseif (!empty($report['file_id']) && in_array($report['proof_type'], ['photo', 'video'])) {
            $media_result = sendMedia($response_chat_id, $report['file_id'], $report['proof_type'], $caption);
            $media_data = json_decode($media_result, true);            
            if ($media_data && $media_data['ok']) {                        
                $report_message_id = $media_data['result']['message_id'];
            }
        }                                                          
        else {
            $text_result = sendMessage($response_chat_id, $caption);
            $text_data = json_decode($text_result, true);              
            if ($text_data && $text_data['ok']) {
                $report_message_id = $text_data['result']['message_id'];
            }
        }
                                                                   
        // Створюємо кнопки з message_id повідомлення з інфою
        $callback_data = $report_message_id ? "{$report['id']}_{$report_message_id}" : "{$report['id']}_0";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ ' . t('accept', $lang), 'callback_data' => "accept_" . $callback_data],
                    ['text' => '❌ ' . t('reject', $lang), 'callback_data' => "reject_" . $callback_data]
                ]
            ]
        ];

        // Відправляємо повідомлення з кнопками і зберігаємо його ID для майбутнього видалення
        $action_message = "🔘 <b>" . t('select_action', $lang) . "</b>";                                                      
        $action_result = sendMessage($response_chat_id, $action_message, $keyboard);                                          
        $action_data = json_decode($action_result, true);
        if ($action_data && $action_data['ok']) {
            $action_message_id = $action_data['result']['message_id'];

            // Додаємо ID повідомлення з кнопками до сесії для майбутнього видалення
            if (!isset($admin_action_sessions[$response_chat_id])) {
                $admin_action_sessions[$response_chat_id] = [];
            }
            $admin_action_sessions[$response_chat_id]['action_message_id'] = $action_message_id;
        }
    } else {
        // Якщо репорт вже оброблений - просто відправляємо інфо
        if ($report['proof_type'] === 'multiple_media' && !empty($report['file_id'])) {                                           
            $media_files = json_decode($report['file_id'], true);
            if (is_array($media_files) && count($media_files) > 0) {
                sendMediaGroup($response_chat_id, $media_files, $caption);
            }                                                      
        }
        elseif (!empty($report['file_id']) && in_array($report['proof_type'], ['photo', 'video'])) {
            sendMedia($response_chat_id, $report['file_id'], $report['proof_type'], $caption);
        }
        else {                                                         
            sendMessage($response_chat_id, $caption);
        }
    }
}
                                                               
function getStatusText($status, $lang) {
    return match($status) {
        'pending' => '⏳ ' . t('status_pending', $lang),
        'accepted' => '✅ ' . t('status_accepted', $lang),
        'rejected' => '❌ ' . t('status_rejected', $lang),         
        default => '🔹 ' . t('status_unknown', $lang)
    };                                                     
}
                                                               
// Прийняття репорту
function handleAcceptReport($response_chat_id, $parts, $current_rank, $admin_id, $lang) {
    global $db;                                                                                                           
    if (!hasPermission('moderator', $current_rank)) {
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);
        return;
    }
                                                               
    $report_id = $parts[1];
    $admin_notes = isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : t('report_accepted_default', $lang);

    $report = $db->getReportById($report_id);
                                                               
    if (!$report) {
        sendMessage($response_chat_id, t('report_not_found', $lang, [$report_id]), null, true);
        return;                                                
    }

    if ($report['status'] !== 'pending') {
        sendMessage($response_chat_id, t('report_already_processed', $lang, [$report_id]), null, true);
        return;
    }                                                      
    
    if ($db->updateReportStatus($report_id, 'accepted', $admin_notes, $admin_id)) {
        sendMessage($response_chat_id, t('report_accepted', $lang, [$report_id, $admin_notes]));

        // Сповіщаємо користувача - на його мові                   
        $target_lang = $db->getUserLanguage($report['user_id']) ?? 'uk';
        $user_message = t('your_report_accepted', $target_lang, [$report_id]) . "\n\n";
        $user_message .= "👤 " . t('your_nick', $target_lang) . ": <code>{$report['reporter_nick']}</code>\n";                
        $user_message .= "🚫 " . t('violator', $target_lang) . ": <code>{$report['reported_nick']}</code>\n";                 
        $user_message .= "📋 " . t('reason', $target_lang) . ": <code>{$report['reason']}</code>\n";
        $user_message .= "💬 " . t('admin_comment', $target_lang) . ": {$admin_notes}\n\n";
        $user_message .= t('thanks_for_help', $target_lang);

        sendMessage($report['user_id'], $user_message);

        logInfo("Репорт #{$report_id} прийнято адміном {$admin_id}");
    } else {
        sendMessage($response_chat_id, t('report_accept_error', $lang), null, true);
    }
}

// Відхилення репорту
function handleRejectReport($response_chat_id, $parts, $current_rank, $admin_id, $lang) {
    global $db;

    if (!hasPermission('moderator', $current_rank)) {
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);
        return;
    }

    $report_id = $parts[1];
    $reject_reason = isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : t('report_rejected_default', $lang);

    $report = $db->getReportById($report_id);

    if (!$report) {
        sendMessage($response_chat_id, t('report_not_found', $lang, [$report_id]), null, true);
        return;
    }

    if ($report['status'] !== 'pending') {
        sendMessage($response_chat_id, t('report_already_processed', $lang, [$report_id]), null, true);
        return;
    }

    if ($db->updateReportStatus($report_id, 'rejected', $reject_reason, $admin_id)) {
        sendMessage($response_chat_id, t('report_rejected', $lang, [$report_id, $reject_reason]));
                                                                   
        // Сповіщаємо користувача - на його мові
        $target_lang = $db->getUserLanguage($report['user_id']) ?? 'uk';
        $user_message = t('your_report_rejected', $target_lang, [$report_id]) . "\n\n";                                       
        $user_message .= "👤 " . t('your_nick', $target_lang) . ": <code>{$report['reporter_nick']}</code>\n";                
        $user_message .= "🚫 " . t('violator', $target_lang) . ": <code>{$report['reported_nick']}</code>\n";
        $user_message .= "📋 " . t('reason', $target_lang) . ": <code>{$report['reason']}</code>\n";
        $user_message .= "💬 " . t('rejection_reason', $target_lang) . ": {$reject_reason}\n\n";
        $user_message .= t('appeal_instruction', $target_lang);

        sendMessage($report['user_id'], $user_message);

        logInfo("Репорт #{$report_id} відхилено адміном {$admin_id}");
    } else {
        sendMessage($response_chat_id, t('report_reject_error', $lang), null, true);
    }
}                                                          
// Глобальна змінна для сесій адмін-дій
$admin_action_sessions = [];                               
// Функція для обробки адмін-сесій (прийняття/відхилення репортів)
function handleAdminActionSession($chat_id, $text, $from, $lang) {
    global $db, $admin_action_sessions;

    $session = $admin_action_sessions[$chat_id] ?? null;
    if (!$session) return false;
                                                               
    // 🔥 ДОДАЄМО ПЕРЕВІРКУ /cancel НА ПОЧАТКУ
    if ($text === '/cancel') {
        // Видаляємо повідомлення з інструкціями
        if (isset($session['instruction_message_id'])) {
            deleteMessage($chat_id, $session['instruction_message_id']);                                                      
        }
        // Видаляємо повідомлення "Оберіть дію" якщо є             
        if (isset($session['action_message_id'])) {
            deleteMessage($chat_id, $session['action_message_id']);
        }                                                          
        unset($admin_action_sessions[$chat_id]);                   
        sendMessage($chat_id, "❌ " . t('admin_action_cancelled', $lang));                                                    
        return true;
    }                                                      
    
    $report_id = $session['report_id'];
    $report = $db->getReportById($report_id);

    if (!$report || $report['status'] !== 'pending') {
        // Видаляємо повідомлення "Оберіть дію" якщо репорт вже оброблений
        if (isset($session['action_message_id'])) {
            deleteMessage($chat_id, $session['action_message_id']);
        }
        unset($admin_action_sessions[$chat_id]);                   
        sendMessage($chat_id, "❌ " . t('report_already_processed', $lang));
        return true;                                           
    }

    // Обробка прийняття
    if ($session['action'] === 'accept') {
        if ($db->updateReportStatus($report_id, 'accepted', $text, $session['user_id'])) {
            // Видаляємо повідомлення з інструкціями
            if (isset($session['instruction_message_id'])) {
                deleteMessage($chat_id, $session['instruction_message_id']);
            }

            // ВИДАЛЯЄМО повідомлення "Оберіть дію"
            if (isset($session['action_message_id'])) {
                deleteMessage($chat_id, $session['action_message_id']);                                                               
                logInfo("🗑️ Видалено повідомлення 'Оберіть дію' при прийнятті репорту #$report_id");
            }
                                                                       
            // Відправляємо повідомлення про успішне прийняття
            sendMessage($chat_id, "✅ <b>Репорт #{$report_id} успішно прийнято!</b>\n💬 Коментар: {$text}");          
            // Сповіщаємо користувача
            $target_lang = $db->getUserLanguage($report['user_id']) ?? 'uk';
            $user_message = t('your_report_accepted', $target_lang, [$report_id]) . "\n\n" .
                "💬 " . t('admin_comment', $target_lang) . ": {$text}";

            sendMessage($report['user_id'], $user_message);
            logInfo("✅ Репорт #{$report_id} прийнято адміном {$session['user_id']}");
        } else {
            sendMessage($chat_id, "❌ " . t('report_accept_error', $lang), null, true);
        }
    }
    // Обробка відхилення
    elseif ($session['action'] === 'reject') {
        if ($db->updateReportStatus($report_id, 'rejected', $text, $session['user_id'])) {
            // Видаляємо повідомлення з інструкціями                   
            if (isset($session['instruction_message_id'])) {
                deleteMessage($chat_id, $session['instruction_message_id']);
            }

            // ВИДАЛЯЄМО повідомлення "Оберіть дію"
            if (isset($session['action_message_id'])) {
                deleteMessage($chat_id, $session['action_message_id']);                                                               
                logInfo("🗑️ Видалено повідомлення 'Оберіть дію' при відхиленні репорту #$report_id");
            }
                                                                       
            // Відправляємо повідомлення про успішне відхилення
            sendMessage($chat_id, "❌ <b>Репорт #{$report_id} успішно відхилено!</b>\n💬 Причина: {$text}");          
            // Сповіщаємо користувача
            $target_lang = $db->getUserLanguage($report['user_id']) ?? 'uk';
            $user_message = t('your_report_rejected', $target_lang, [$report_id]) . "\n\n" .
                "💬 " . t('rejection_reason', $target_lang) . ": {$text}";                                            
            sendMessage($report['user_id'], $user_message);

            logInfo("❌ Репорт #{$report_id} відхилено адміном {$session['user_id']}");                                       
        } else {
            sendMessage($chat_id, "❌ " . t('report_reject_error', $lang), null, true);
        }
    }                                                      
    
    // Завершуємо сесію                                        
    unset($admin_action_sessions[$chat_id]);
    return true;
}

// Оновлена функція для обробки адмін-кнопок репортів      
function handleAdminReportCallback($callback_data, $chat_id, $message_id, $from, $lang) {                                 
    global $db, $admin_action_sessions;
                                                               
    logInfo("🎯 CALLBACK ОТРИМАНО: $callback_data від $chat_id");

    // Перевіряємо чи це кнопка прийняття
    if (strpos($callback_data, 'accept_') === 0) {
        $parts = explode('_', $callback_data);
        $report_id = $parts[1];
        $report_message_id = $parts[2] ?? null;
                                                                   
        logInfo("✅ Обробка прийняття репорту #$report_id, message_id: $report_message_id");

        $report = $db->getReportById($report_id);
                                                                   
        if ($report && $report['status'] === 'pending') {
            // ЗБЕРІГАЄМО ID повідомлення з кнопками для видалення                                                                
            $action_message_id = $message_id;

            // Відправляємо повідомлення з інструкціями
            $instruction_message = sendMessage($chat_id,
                "✅ " . t('you_accepting_report', $lang, [$report_id]) . "\n\n" .
                "📝 " . t('please_enter_comment', $lang) . ":\n" .
                "💡 <i>" . t('comment_examples', $lang) . "</i>\n\n" .                                                                
                "❌ " . t('to_cancel_enter', $lang) . " /cancel"
            );                                             
            $instruction_data = json_decode($instruction_message, true);
            $instruction_message_id = $instruction_data['result']['message_id'] ?? null;

            // Запускаємо сесію з ID повідомлення з кнопками
            $admin_action_sessions[$chat_id] = [
                'action' => 'accept',
                'report_id' => $report_id,                                 
                'report_message_id' => $report_message_id,
                'button_message_id' => $message_id,
                'action_message_id' => $action_message_id, // Додаємо ID повідомлення "Оберіть дію"                                   
                'instruction_message_id' => $instruction_message_id,
                'user_id' => $from['id'],
                'lang' => $lang
            ];
                                                                       
            logInfo("📝 СЕСІЯ СТВОРЕНА для репорту #$report_id, action_message_id: $action_message_id");
        }
    }
    // Перевіряємо чи це кнопка відхилення
    elseif (strpos($callback_data, 'reject_') === 0) {
        $parts = explode('_', $callback_data);
        $report_id = $parts[1];
        $report_message_id = $parts[2] ?? null;

        logInfo("❌ Обробка відхилення репорту #$report_id, message_id: $report_message_id");
                                                                   
        $report = $db->getReportById($report_id);
                                                                   
        if ($report && $report['status'] === 'pending') {
            // ЗБЕРІГАЄМО ID повідомлення з кнопками для видалення                                                                
            $action_message_id = $message_id;
                                                                       
            // Відправляємо повідомлення з інструкціями
            $instruction_message = sendMessage($chat_id,
                "❌ " . t('you_rejecting_report', $lang, [$report_id]) . "\n\n" .
                "📝 " . t('please_enter_rejection_reason', $lang) . ":\n" .                                                           
                "💡 <i>" . t('rejection_examples', $lang) . "</i>\n\n" .                                                              
                "❌ " . t('to_cancel_enter', $lang) . " /cancel"
            );

            $instruction_data = json_decode($instruction_message, true);                                                          
            $instruction_message_id = $instruction_data['result']['message_id'] ?? null;

            // Запускаємо сесію з ID повідомлення з кнопками
            $admin_action_sessions[$chat_id] = [
                'action' => 'reject',
                'report_id' => $report_id,                                 
                'report_message_id' => $report_message_id,
                'button_message_id' => $message_id,
                'action_message_id' => $action_message_id, // Додаємо ID повідомлення "Оберіть дію"
                'instruction_message_id' => $instruction_message_id,
                'user_id' => $from['id'],
                'lang' => $lang                                        
            ];
                                                                       
            logInfo("📝 СЕСІЯ СТВОРЕНА для репорту #$report_id, action_message_id: $action_message_id");
        }
    }
    else {                                                         
        logWarning("❌ Невідомий callback: $callback_data");
    }
}
                                                               
// РЕШТА ФУНКЦІЙ
                                                               
function handleDebugToggle($response_chat_id, $current_rank, $lang) {
    global $config;                                                                                                       
    if (!hasPermission('owner', $current_rank)) {                  
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);                                                
        return;
    }
                                                               
    $current_debug = $config['debug_mode'] ?? false;
    $new_debug = !$current_debug;

    $config['debug_mode'] = $new_debug;

    $config_path = __DIR__ . '/../config.php';
                                                               
    try {
        $new_config_content = "<?php\nreturn " . var_export($config, true) . ";\n?>";
                                                                   
        if (file_put_contents($config_path, $new_config_content) !== false) {
            $status = $new_debug ? t('debug_enabled', $lang) : t('debug_disabled', $lang);
            sendMessage($response_chat_id, t('logging_mode', $lang) . ": $status");

            if ($new_debug) {                                              
                logDebug("🔍 DEBUG: Тест детального режиму");                                                                         
                logInfo("🔍 INFO: Тест детального режиму");
            }
                                                                       
            logInfo("Логування змінено на: " . ($new_debug ? 'DEBUG' : 'INFO') . " користувачем $response_chat_id");
        } else {
            sendMessage($response_chat_id, t('save_error', $lang), null, true);
        }
    } catch (Exception $e) {
        sendMessage($response_chat_id, t('error', $lang), null, true);                                                        
        logError("Помилка зміни логування: " . $e->getMessage());
    }
}

function handleAddAdmin($response_chat_id, $parts, $lang, $current_rank) {
    global $db;
    logInfo("ADD_ADMIN " . implode(" ", $parts));

    if (!hasPermission('admin', $current_rank)) {
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);
        logWarning("Спроба додати адміна без прав: $response_chat_id");
        return;
    }

    if (count($parts) < 3) {
        $usage_message = "ℹ️ <b>" . t('usage', $lang) . ":</b> /addadmin [user_id/@username] [rank]\n\n";
        $usage_message .= "📝 <b>" . t('examples', $lang) . ":</b>\n";
        $usage_message .= "• <code>/addadmin 123456789 moderator</code>\n";
        $usage_message .= "• <code>/addadmin @username admin</code>\n\n";
        $usage_message .= "🎯 <b>" . t('available_ranks', $lang) . ":</b> moderator, admin, owner\n\n";
        $usage_message .= "💡 <b>" . t('important', $lang) . ":</b> " . t('user_must_be_in_database', $lang);
        sendMessage($response_chat_id, $usage_message, null, true);
        return;
    }

    $identifier = trim($parts[1]);
    $rank = strtolower($parts[2]);

    // Перевіряємо чи це username (починається з @)
    $is_username = (strpos($identifier, '@') === 0);
    
    $user_id = null;
    $username = null;
    $first_name = null;
    
    if ($is_username) {
        $username_input = substr($identifier, 1);
        $username_input = strtolower($username_input);
        
        logInfo("Пошук користувача за username: $username_input");
        
        $user = null;
        
        try {
            $user = $db->query("SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1", false, [$username_input]);
        } catch (Exception $e) {
            logError("Помилка пошуку: " . $e->getMessage());
        }

        if (!$user) {
            $error_message = "❌ <b>" . t('user_not_found', $lang, ["@$username_input"]) . "</b>\n\n";
            $error_message .= "💡 <b>" . t('possible_reasons', $lang) . ":</b>\n";
            $error_message .= "• " . t('user_never_wrote_to_bot', $lang) . "\n";
            $error_message .= "• " . t('username_incorrect', $lang) . "\n";
            $error_message .= "• " . t('user_changed_username', $lang) . "\n\n";
            $error_message .= "🔧 <b>" . t('what_to_do', $lang) . ":</b>\n";
            $error_message .= "• " . t('ask_user_to_write_bot', $lang) . "\n";
            $error_message .= "• " . t('use_numeric_id_instead', $lang) . "\n";
            $error_message .= "• " . t('check_username_correctness', $lang);
            
            sendMessage($response_chat_id, $error_message, null, true);
            logWarning("Користувача @$username_input не знайдено в базі");
            return;
        }
        
        $user_id = $user['user_id'];
        $first_name = $user['first_name'] ?: t('user', $lang);
        $username = $user['username'] ?: $username_input;
        
        logInfo("Користувач знайдений: ID: $user_id, Username: $username, Name: $first_name");
        
    } else {
        if (!preg_match('/^-?\d+$/', $identifier)) {
            sendMessage($response_chat_id, "❌ <b>" . t('invalid_id_format', $lang) . "</b>\n\n" . t('use_numeric_id_or_username', $lang), null, true);
            return;
        }
        
        $user_id = $identifier;
        
        $user = $db->query("SELECT * FROM users WHERE user_id = ? LIMIT 1", false, [$user_id]);
        if ($user) {
            $first_name = $user['first_name'] ?: t('user', $lang);
            $username = $user['username'] ?? null;
        } else {
            $first_name = t('user_id', $lang) . ": $user_id";
            $username = null;
            
            $warning_message = "⚠️ <b>" . t('user_id_not_found', $lang, [$user_id]) . "</b>\n\n";
            $warning_message .= "💡 <i>" . t('admin_will_be_added_with_limitations', $lang) . "</i>";
            sendMessage($response_chat_id, $warning_message, null, true);
        }
    }

    if (!in_array($rank, ['moderator', 'admin', 'owner'])) {
        sendMessage($response_chat_id, "❌ <b>" . t('invalid_rank', $lang) . "</b>\n\n" . t('available_ranks_list', $lang), null, true);
        return;
    }

    if ($current_rank === 'admin' && $rank !== 'moderator') {
        sendMessage($response_chat_id, t('admin_can_add_only_moderator', $lang), null, true);
        return;
    }

    $existing_admin = $db->query("SELECT * FROM admins WHERE user_id = ?", false, [$user_id]);
    if ($existing_admin) {
        $existing_rank = $existing_admin['rank'] ?? 'moderator';
        
        $already_admin_message = "⚠️ <b>" . t('user_already_admin', $lang) . "</b>\n\n";
        $already_admin_message .= "👤 <b>" . t('user', $lang) . ":</b> " . ($first_name ?? t('no_name', $lang)) . "\n";
        if ($username) {
            $already_admin_message .= "📱 <b>Username:</b> @$username\n";
        }
        $already_admin_message .= "🛡️ <b>" . t('current_rank', $lang) . ":</b> $existing_rank\n\n";
        
        if ($existing_rank === $rank) {
            $already_admin_message .= "ℹ️ <i>" . t('admin_already_has_rank', $lang, [$rank]) . "</i>";
        } else {
            $already_admin_message .= "💡 <i>" . t('use_setrank_to_change', $lang) . "</i>";
        }
        
        sendMessage($response_chat_id, $already_admin_message, null, true);
        return;
    }

    $success = false;
    
    try {
        if (method_exists($db, 'addAdmin')) {
            $success = $db->addAdmin($user_id, $username, $first_name, $rank);
        } else {
            $stmt = $db->pdo->prepare("INSERT INTO admins (user_id, username, first_name, rank, added_at) VALUES (?, ?, ?, ?, datetime('now'))");
            $success = $stmt->execute([$user_id, $username, $first_name, $rank]);
        }
        
        if ($success) {
            $check_admin = $db->query("SELECT * FROM admins WHERE user_id = ?", false, [$user_id]);
            if ($check_admin) {
                logInfo("✅ Адмін успішно доданий в базу: " . ($username ? "@$username" : "ID: $user_id") . " ранг: $rank");
                $success = true;
            } else {
                logError("❌ Адмін не додався в базу навіть після успішного INSERT");
                $success = false;
            }
        }
        
    } catch (Exception $e) {
        logError("Помилка додавання адміна в базу: " . $e->getMessage());
        $success = false;
    }

    if ($success) {
        $success_message = "✅ <b>" . t('admin_added_successfully', $lang) . "</b>\n\n";
        $success_message .= "👤 <b>" . t('user', $lang) . ":</b> " . ($first_name ?? t('no_name', $lang)) . "\n";
        if ($username) {
            $success_message .= "📱 <b>Username:</b> @$username\n";
        }
        $success_message .= "🆔 <b>ID:</b> <code>$user_id</code>\n";
        $success_message .= "🛡️ <b>" . t('rank', $lang) . ":</b> $rank\n";
        
        sendMessage($response_chat_id, $success_message, null, true);

        $target_lang = $db->getUserLanguage($user_id) ?? 'uk';
        $user_message = t('you_appointed_admin', $target_lang, [$rank]);
        
        $send_result = sendMessage($user_id, $user_message, null, true);
        if (!$send_result) {
            $warning_msg = "⚠️ <b>" . t('failed_to_send_notification', $lang) . "</b>\n\n";
            $warning_msg .= "💡 <i>" . t('user_may_have_blocked_bot', $lang) . "</i>";
            sendMessage($response_chat_id, $warning_msg, null, true);
        }

        logInfo("🎉 Адмін повністю доданий: " . ($username ? "@$username" : "ID: $user_id") . " ранг: $rank");
    } else {
        $error_msg = "❌ <b>" . t('admin_add_error', $lang) . "</b>\n\n";
        $error_msg .= "💡 <i>" . t('try_again_or_contact_developer', $lang) . "</i>";
        sendMessage($response_chat_id, $error_msg, null, true);
        logError("Помилка додавання адміна: " . ($username ? "@$username" : "ID: $user_id"));
    }
}

function handleRemoveAdmin($response_chat_id, $parts, $lang, $current_rank) {
    global $db;

    logInfo("REMOVE_ADMIN " . implode(" ", $parts));
                                                               
    if (!hasPermission('admin', $current_rank)) {
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);                                                
        logWarning("Спроба видалити адміна без прав: $response_chat_id");
        return;
    }

    if (count($parts) < 2) {
        sendMessage($response_chat_id, t('removeadmin_usage', $lang), null, true);
        return;
    }

    $identifier = trim($parts[1]);

    // Отримуємо дані адміна за ідентифікатором
    $admin_data = $db->getAdminByIdentifier($identifier);      
    if (!$admin_data) {
        sendMessage($response_chat_id, t('admin_not_found', $lang, [$identifier]), null, true);                               
        logWarning("Адмін не знайдений для видалення: $identifier");
        return;
    }                                                      
    $admin_id = $admin_data['user_id'] ?? $admin_data['chat_id'] ?? null;

    // Видаляємо адміна                                        
    if ($db->removeAdmin($admin_id)) {
        sendMessage($response_chat_id, t('admin_removed', $lang, [$identifier]));

        // Сповіщаємо видаленого адміна                            
        if ($admin_id) {
            $target_lang = $db->getUserLanguage($admin_id) ?? 'uk';
            sendMessage($admin_id, t('you_removed_from_admins', $target_lang));                                               
        }
                                                               
        logInfo("Адмін успішно видалений: $identifier");       
    } else {                                                      
        sendMessage($response_chat_id, t('admin_remove_error', $lang), null, true);                                           
        logError("Помилка видалення адміна: $identifier");
    }                                                      
}                                                          
function handleSetRank($response_chat_id, $parts, $lang, $current_rank) {
    global $db;

    logInfo("SET_RANK " . implode(" ", $parts));
                                                               
    if (!hasPermission('admin', $current_rank)) {                  
        sendMessage($response_chat_id, t('no_permission', $lang), null, true);
        logWarning("Спроба змінити ранг без прав: $response_chat_id");
        return;
    }                                                                                                                     
    if (count($parts) < 3) {                                       
        sendMessage($response_chat_id, t('setrank_usage', $lang), null, true);
        return;
    }

    $identifier = trim($parts[1]);
    $rank = strtolower($parts[2]); // Ось де має бути визначена $rank

    // Отримуємо дані адміна за ідентифікатором
    $admin_data = $db->getAdminByIdentifier($identifier);
    if (!$admin_data) {
        sendMessage($response_chat_id, t('admin_not_found', $lang, [$identifier]), null, true);
        logWarning("Адмін не знайдений для зміни рангу: $identifier");
        return;
    }                                                      
    $admin_id = $admin_data['user_id'] ?? $admin_data['chat_id'] ?? null;

    if (!in_array($rank, ['moderator', 'admin', 'owner'])) {
        sendMessage($response_chat_id, t('invalid_rank', $lang), null, true);
        return;                                                
    }

    if ($db->setAdminRank($admin_id, $rank)) {
        // Тому хто змінив - на його мові                          
        sendMessage($response_chat_id, t('rank_updated', $lang, [$rank]));                                            
        // Тому кому змінили - на його мові
        $target_lang = $db->getUserLanguage($admin_id) ?? 'uk';                                                               
        sendMessage($admin_id, t('your_rank_updated', $target_lang, [$rank]));

        logInfo("Ранг адміна оновлено: $identifier -> $rank");                                                            
    } else {
        sendMessage($response_chat_id, t('rank_update_error', $lang), null, true);
        logError("Помилка оновлення рангу: $identifier -> $rank");
    }
}

function handleAdminList($response_chat_id, $lang) {
    global $db;

    logDebug("ADMIN_LIST requested by $response_chat_id");

    $admins = $db->getAllAdmins();                             
    if (empty($admins)) {
        sendMessage($response_chat_id, t('no_admins', $lang), null, true);
        return;                                                
    }

    $message = t('admin_list', $lang) . ":\n";
    foreach ($admins as $admin) {
        $rank_emoji = match($admin['rank']) {
            'owner' => '👑',                                           
            'admin' => '⭐',
            'moderator' => '🛡️',
            default => '🔹'
        };                                                 
        $username_display = $admin['username'] ? "@" . $admin['username'] : t('no_username', $lang);
        $user_id_display = $admin['user_id'] ?: t('not_set', $lang);
        $first_name_display = $admin['first_name'] ?: t('no_name', $lang);

        $message .= "$rank_emoji {$admin['rank']} • $first_name_display • $username_display • ID: $user_id_display\n";    
    }

    sendMessage($response_chat_id, $message, null, true);
    logDebug("ADMIN_LIST sent to $response_chat_id");
}

function handleAdminStats($response_chat_id, $lang, $current_rank) {
    global $db;                                            
    logDebug("STATS requested by $response_chat_id");

    $stats = $db->getStats();
                                                               
    $message = t('stats_admin', $lang) . ":\n";
    $message .= t('total_users', $lang) . ": " . $stats['total_users'] . "\n";
    $message .= t('total_admins', $lang) . ": " . $stats['total_admins'] . "\n";                                          
    $message .= t('your_rank', $lang) . ": " . $current_rank;

    sendMessage($response_chat_id, $message, null, true);
}
                                                               
function handleMyRank($response_chat_id, $lang, $current_rank) {
    logDebug("MYRANK requested by $response_chat_id");

    $message = t('your_rank', $lang) . ": " . $current_rank . "\n";                                                       
    $message .= t('permissions', $lang) . ": ";

    switch ($current_rank) {                                       
        case 'owner': $message .= t('owner_permissions', $lang); break;
        case 'admin': $message .= t('admin_permissions', $lang); break;
        case 'moderator': $message .= t('moderator_permissions', $lang); break;
    }                                                      
    
    sendMessage($response_chat_id, $message, null, true);
}
?>