<?php
// Глобальна змінна для відстеження стану репортів
$report_sessions = [];

function handleUserCommand($chat_id, $text, $from, $lang, $is_admin, $current_rank) {
    global $report_sessions, $db;

    // Перевіряємо чи користувач в процесі створення репорту
    if (isset($report_sessions[$chat_id])) {
        handleReportSession($chat_id, $text, $from, $lang);
        return;
    }

    switch ($text) {
        case "/start":
            $message = t('start', $lang);
            if ($is_admin) {
                $message .= "\n🎖️ " . t('your_rank', $lang) . ": " . $current_rank;
            }
            $message .= "\n" . t('write_help', $lang);
            sendMessage($chat_id, $message);
            break;

        case "/help":
            $message = t('help', $lang);

            if ($is_admin) {
                $message .= "\n\n" . t('admin_commands', $lang) . ":\n";
                $message .= "/adminlist - " . t('admin_list_desc', $lang) . "\n";
                $message .= "/reports - " . t('reports_list_pending', $lang) . "\n";
                $message .= "/report ID - " . t('view_report', $lang) . "\n";
                $message .= "/accept ID - " . t('accept_desc', $lang) . "\n";
                $message .= "/reject ID - " . t('reject_desc', $lang) . "\n";

                if (in_array($current_rank, ['admin', 'owner'])) {
                    $message .= "/addadmin - " . t('addadmin_desc', $lang) . "\n";
                    $message .= "/removeadmin - " . t('removeadmin_desc', $lang) . "\n";
                }

                if ($current_rank === 'owner') {
                    $message .= "/setrank - " . t('setrank_desc', $lang) . "\n";
                    $message .= "/debug - " . t('debug_desc', $lang) . "\n";
                    $message .= "/broadcast - " . t('broadcast_desc', $lang) . "\n";
                }
            }

            // 🔥 ВИПРАВЛЕНО: Загальні команди з перекладом
            $message .= "\n\n❌ " . t('general_commands', $lang) . ":\n";
            $message .= "/cancel - " . t('cancel_desc', $lang);

            sendMessage($chat_id, $message);
            break;

        case "/about":
            sendMessage($chat_id, t('about', $lang));
            break;

        case "/myid":
            $user_id = (string)$from['id'];
            $chat_id_display = $chat_id;
            $username = $from['username'] ?? t('not_specified', $lang);
            $first_name = $from['first_name'] ?? t('not_specified', $lang);

            $user_info = "📋 <b>" . t('your_data', $lang) . ":</b>\n";
            $user_info .= "🆔 <b>User ID:</b> <code>$user_id</code>\n";
            $user_info .= "💬 <b>Chat ID:</b> <code>$chat_id_display</code>\n";
            $user_info .= "👤 <b>" . t('name', $lang) . ":</b> $first_name\n";
            $user_info .= "📛 <b>Username:</b> @$username\n";

            // Визначення типу чату
            if ($chat_id_display > 0) {
                $user_info .= "📍 <b>" . t('chat_type', $lang) . ":</b> " . t('chat_type_private', $lang);
            } elseif ($chat_id_display < -1000000000000) {
                $user_info .= "📍 <b>" . t('chat_type', $lang) . ":</b> " . t('chat_type_supergroup', $lang);
            } elseif ($chat_id_display < 0) {
                $user_info .= "📍 <b>" . t('chat_type', $lang) . ":</b> " . t('chat_type_group', $lang);
            }

            sendMessage($chat_id, $user_info);
            writeLog("User checked IDs: user=$user_id, chat=$chat_id_display");
            break;

        case "/stats":
            $stats = getStats();
            $rank_info = $is_admin ? "🎖️ " . t('your_rank', $lang) . ": " . $current_rank : t('regular_user', $lang);

            $message = t('stats', $lang, [$stats['total_users'], $stats['total_admins'], $rank_info]);

            // Додаємо статистику репортів якщо є дані
            if (isset($stats['total_reports'])) {
                $message .= "\n🚨 " . t('reports_total', $lang, [$stats['total_reports'], $stats['pending_reports']]);
            }

            sendMessage($chat_id, $message);
            break;

        case "/myrank":
            if ($is_admin) {
                $message = "🎖️ " . t('your_rank', $lang) . ": " . $current_rank . "\n";
                $message .= "📋 " . t('permissions', $lang) . ": ";
                switch ($current_rank) {
                    case 'owner': $message .= t('owner_permissions', $lang); break;
                    case 'admin': $message .= t('admin_permissions', $lang); break;
                    case 'moderator': $message .= t('moderator_permissions', $lang); break;
                }
            } else {
                $message = t('myrank_user', $lang);
            }
            sendMessage($chat_id, $message);
            break;

        case "/report":
            startReportSession($chat_id, $from, $lang);
            break;

        case "/cancel":
            handleCancelCommand($chat_id, $lang);
            break;

        default:
            if (strpos($text, '/') === 0) {
                sendMessage($chat_id, t('unknown_command', $lang));
            }
            break;
    }

    if (strpos($text, '/') === 0) {
        writeLog("Command from $chat_id: $text" . ($is_admin ? " (admin, rank: " . $current_rank . ")" : " (user)"));
    }
}

// Функція для початку процесу репорту
function startReportSession($chat_id, $from, $lang) {
    global $report_sessions;

    $report_sessions[$chat_id] = [
        'step' => 1,
        'user_id' => $from['id'],
        'data' => [
            'media_files' => [] // Масив для зберігання медіа-файлів
        ],
        'lang' => $lang
    ];

    sendMessage($chat_id,
        "🚨 <b>" . t('report_system', $lang) . "</b>\n\n" .
        t('report_step1_desc', $lang) . "\n\n" .
        "<b>" . t('report_step', $lang, [1, 4]) . "</b>\n" .
        "📛 " . t('report_your_nick', $lang) . ":\n" .
        "<code>" . t('report_example', $lang) . ": VitalikBee11</code>\n\n" .
        "❌ " . t('to_cancel_process', $lang)  // ✅ ТІЛЬКИ ОДНА ІНСТРУКЦІЯ СКАСУВАННЯ
    );
}

// Функція для обробки кроків репорту
function handleReportSession($chat_id, $text, $from, $lang) {
    global $report_sessions, $db;

    $session = $report_sessions[$chat_id];

    // 🔥 ПЕРЕВІРКА /cancel
    if ($text === '/cancel') {
        unset($report_sessions[$chat_id]);
        sendMessage($chat_id, "❌ " . t('report_creation_cancelled', $lang));
        return;
    }

    switch ($session['step']) {
        case 1: // Очікуємо ігровий нік репортера
            if (empty(trim($text)) || $text === '/report') {
                sendMessage($chat_id, t('report_enter_nick', $lang));
                return;
            }

            $report_sessions[$chat_id]['data']['reporter_nick'] = trim($text);
            $report_sessions[$chat_id]['step'] = 2;

            sendMessage($chat_id,
                "👤 <b>" . t('report_step', $lang, [2, 4]) . "</b>\n" .
                "📛 " . t('report_violator_nick', $lang) . ":\n" .
                "<code>" . t('report_example', $lang) . ": Danylchik123</code>\n\n" .
                "❌ " . t('to_cancel_process', $lang)  //  ✅ ТІЛЬКИ ОДНА ІНСТРУКЦІЯ СКАСУВАННЯ
            );
            break;

        case 2: // Очікуємо нік порушника
            if (empty(trim($text)) || $text === '/report') {
                sendMessage($chat_id, t('report_enter_violator_nick', $lang));
                return;
            }

            $report_sessions[$chat_id]['data']['reported_nick'] = trim($text);
            $report_sessions[$chat_id]['step'] = 3;

            $reasons = t('report_reasons', $lang);

            sendMessage($chat_id,
                "📋 <b>" . t('report_step', $lang, [3, 4]) . "</b>\n" .
                "❓ " . t('report_reason', $lang) . ":\n" .
                "<code>" . t('report_choose_reason', $lang) . ":</code>\n\n" .
                $reasons . "\n\n" .
                "<code>" . t('report_example', $lang) . ": 1.1</code>\n\n" .
                "❌ " . t('to_cancel_process', $lang)  //  ✅ ТІЛЬКИ ОДНА ІНСТРУКЦІЯ СКАСУВАННЯ
            );
            break;

        case 3: // Очікуємо причину
            if (empty(trim($text)) || $text === '/report') {
                sendMessage($chat_id, t('report_enter_reason', $lang));
                return;
            }

            $report_sessions[$chat_id]['data']['reason'] = trim($text);
            $report_sessions[$chat_id]['step'] = 4;

            sendMessage($chat_id,
                "📷 <b>" . t('report_step', $lang, [4, 4]) . "</b>\n" .
                "🖼️ " . t('report_proof', $lang) . ":\n" .
                "<code>" . t('report_proof_instruction_multiple', $lang) . "</code>\n\n" .
                "📎 " . t('report_multiple_media', $lang) . "\n" .
                "💡 " . t('report_proof_hint', $lang) . "\n\n" .
                "<i>" . t('report_can_add_multiple', $lang) . "</i>\n\n" .
                "❌ " . t('to_cancel_process', $lang)  //  ✅ ТІЛЬКИ ОДНА ІНСТРУКЦІЯ СКАСУВАННЯ
            );

            // Показуємо кнопки для керування репортом
            showReportCompletionButtons($chat_id, $report_sessions[$chat_id]);
            break;

        case 4: // Очікуємо докази або завершення
            $proof = trim($text);

            if (strtolower($proof) === 'готово' || strtolower($proof) === 'done' || strtolower($proof) === 'завершити') {
                // Завершуємо збір медіа
                completeReport($chat_id, $session);
            } elseif ($proof === '/report') {
                // Ігноруємо команду /report
                return;
            } else {
                // Якщо користувач написав текст (не медіа)
                if (!empty($proof)) {
                    $report_sessions[$chat_id]['data']['text_proof'] = $proof;
                }

                // Показуємо кнопки замість текстової інструкції
                showReportCompletionButtons($chat_id, $report_sessions[$chat_id]);
            }
            break;
    }
}

// Функція для показу кнопок завершення репорту
function showReportCompletionButtons($chat_id, $session) {
    $media_count = count($session['data']['media_files'] ?? []);
    $has_text_proof = !empty($session['data']['text_proof']);
    $lang = $session['lang'];

    $message = "📦 <b>" . t('report_status_title', $lang) . "</b>\n\n";

    if ($media_count > 0) {
        $photos_count = count(array_filter($session['data']['media_files'] ?? [], function($m) { return $m['type'] === 'photo'; }));
        $videos_count = count(array_filter($session['data']['media_files'] ?? [], function($m) { return $m['type'] === 'video'; }));
        $message .= "✅ " . t('media_files_added', $lang, [$media_count, $photos_count, $videos_count]) . "\n";
    }

    if ($has_text_proof) {
        $message .= "✅ " . t('text_proof_added', $lang) . "\n";
    }

    if ($media_count === 0 && !$has_text_proof) {
        $message .= "📝 " . t('no_proof_added', $lang) . "\n";
    }

    $message .= "\n💡 <i>" . t('choose_action', $lang) . "</i>";
    $message .= "\n\n❌ " . t('to_cancel_process', $lang);

    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ ' . t('finish_report', $lang),
                    'callback_data' => 'report_complete'
                ],
                [
                    'text' => '📸 ' . t('add_photo', $lang),
                    'callback_data' => 'report_add_photo'
                ]
            ],
            [
                [
                    'text' => '🎬 ' . t('add_video', $lang),
                    'callback_data' => 'report_add_video'
                ],
                [
                    'text' => '❌ ' . t('cancel', $lang),
                    'callback_data' => 'report_cancel'
                ]
            ]
        ]
    ];

    sendMessage($chat_id, $message, $keyboard);
}

// Функція для обробки callback кнопок репорту
function handleReportCallback($callback_data, $chat_id, $message_id, $from, $lang) {
    global $report_sessions;

    $session = $report_sessions[$chat_id] ?? null;
    if (!$session) {
        sendMessage($chat_id, t('report_session_not_found', $lang));
        return;
    }

    switch ($callback_data) {
        case 'report_complete':
            completeReport($chat_id, $session);
            break;

        case 'report_add_photo':
            sendMessage($chat_id, "📸 " . t('send_photo_for_report', $lang));
            break;

        case 'report_add_video':
            sendMessage($chat_id, "🎬 " . t('send_video_for_report', $lang));
            break;

        case 'report_cancel':
            unset($report_sessions[$chat_id]);
            sendMessage($chat_id, "❌ " . t('report_creation_cancelled', $lang));
            break;
    }
}

// Функція для додавання медіа до репорту
function addMediaToReport($chat_id, $file_id, $media_type, $caption = '') {
    global $report_sessions;

    if (!isset($report_sessions[$chat_id]) || $report_sessions[$chat_id]['step'] != 4) {
        return false;
    }

    // Додаємо медіа до масиву
    $report_sessions[$chat_id]['data']['media_files'][] = [
        'file_id' => $file_id,
        'type' => $media_type,
        'caption' => $caption
    ];

    $count = count($report_sessions[$chat_id]['data']['media_files']);
    $lang = $report_sessions[$chat_id]['lang'];

    sendMessage($chat_id,
        t('report_media_added', $lang, [$count, $media_type])
    );

    // Показуємо оновлені кнопки
    showReportCompletionButtons($chat_id, $report_sessions[$chat_id]);

    return true;
}

// Функція для обробки медіа повідомлень
function handleMediaMessage($chat_id, $message, $lang) {
    global $report_sessions;

    // Перевіряємо, чи користувач у режимі створення репорту
    if (!isset($report_sessions[$chat_id]) || $report_sessions[$chat_id]['step'] != 4) {
        return false;
    }

    $file_id = null;
    $media_type = null;

    // Визначаємо тип медіа та file_id - ТІЛЬКИ ФОТО ТА ВІДЕО
    if (isset($message['photo'])) {
        $file_id = $message['photo'][count($message['photo']) - 1]['file_id'];
        $media_type = 'photo';
    } elseif (isset($message['video'])) {
        $file_id = $message['video']['file_id'];
        $media_type = 'video';
    }
    // Документи та аудіо ігноруємо

    if ($file_id && $media_type) {
        $caption = $message['caption'] ?? '';
        return addMediaToReport($chat_id, $file_id, $media_type, $caption);
    }

    return false;
}

// Функція для завершення репорту
function completeReport($chat_id, $session) {
    global $db, $report_sessions, $apiURL;

    try {
        // Серіалізуємо масив медіа-файлів для зберігання в БД
        $media_data = !empty($session['data']['media_files']) ?
            json_encode($session['data']['media_files']) : null;

        // Зберігаємо репорт
        $report_id = $db->addReport(
            $session['user_id'],
            $session['data']['reporter_nick'],
            $session['data']['reported_nick'],
            $session['data']['reason'],
            $session['data']['text_proof'] ?? null,
            !empty($session['data']['media_files']) ? 'multiple_media' : 'text',
            $media_data
        );

        if ($report_id) {
            $user_lang = $session['lang'];

            // Текст для користувача (на його мові)
            $user_caption = t('report_created_success', $user_lang) . "\n\n";
            $user_caption .= t('report_details_user', $user_lang, [
                $report_id,
                $session['data']['reporter_nick'],
                $session['data']['reported_nick'],
                $session['data']['reason']
            ]);

            $media_count = count($session['data']['media_files']);
            if ($media_count > 0) {
                $user_caption .= "\n" . t('report_media_count', $user_lang, [$media_count]);
            }

            if (isset($session['data']['text_proof']) && $session['data']['text_proof']) {
                $user_caption .= "\n" . t('report_text_proof_added', $user_lang);
            }

            $user_caption .= "\n\n" . t('admins_notified', $user_lang);

            // Медіа-файли
            $media_files = $session['data']['media_files'];

            // 📧 ВІДПРАВЛЯЄМО АДМІНАМ (на ЇХНІЙ мові)
            $admins = $db->getAllAdmins();
            foreach ($admins as $admin) {
                $admin_lang = $db->getUserLanguage($admin['user_id']) ?? 'uk';

                // Основний текст репорту для адміна (на його мові)
                $admin_caption = t('new_report_title', $admin_lang, [$report_id]) . "\n\n";
                $admin_caption .= t('reporter_label', $admin_lang) . ": <code>{$session['data']['reporter_nick']}</code>\n";
                $admin_caption .= t('violator_label', $admin_lang) . ": <code>{$session['data']['reported_nick']}</code>\n";
                $admin_caption .= t('reason_label', $admin_lang) . ": <code>{$session['data']['reason']}</code>\n";

                if (isset($session['data']['text_proof']) && $session['data']['text_proof']) {
                    $admin_caption .= "📝 <b>" . t('proof', $admin_lang) . ":</b> {$session['data']['text_proof']}\n";
                }

                if ($media_count > 0) {
                    $admin_caption .= "📎 <b>" . t('media_files', $admin_lang) . ":</b> {$media_count}\n";
                }

                $admin_caption .= "\n" . t('status_pending', $admin_lang);

                // Відправляємо репорт адміну (медіа або текст)
                if (count($media_files) > 0) {
                    if (count($media_files) > 1) {
                        sendMediaGroup($admin['user_id'], $media_files, $admin_caption);
                    } else {
                        $media = $media_files[0];
                        sendMedia($admin['user_id'], $media['file_id'], $media['type'], $admin_caption);
                    }
                } else {
                    sendMessage($admin['user_id'], $admin_caption);
                }

                // ⭐ ВІДПРАВЛЯЄМО КНОПКИ АДМІНУ (на його мові)
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '✅ ' . t('accept', $admin_lang),
                                'callback_data' => "accept_{$report_id}_0"
                            ],
                            [
                                'text' => '❌ ' . t('reject', $admin_lang),
                                'callback_data' => "reject_{$report_id}_0"
                            ]
                        ]
                    ]
                ];

                $action_message = "🔘 <b>" . t('select_action', $admin_lang) . "</b>";
                sendMessage($admin['user_id'], $action_message, $keyboard);
            }

            // 👤 ВІДПРАВЛЯЄМО КОРИСТУВАЧЕВІ (на його мові)
            if (count($media_files) > 0) {
                if (count($media_files) > 1) {
                    sendMediaGroup($chat_id, $media_files, $user_caption);
                } else {
                    $media = $media_files[0];
                    sendMedia($chat_id, $media['file_id'], $media['type'], $user_caption);
                }
            } else {
                sendMessage($chat_id, $user_caption);
            }

            logInfo("Створено репорт #{$report_id} від користувача {$session['user_id']} з {$media_count} медіа-файлами");
        } else {
            sendMessage($chat_id, t('report_creation_error', $user_lang));
            logError("Помилка створення репорту для користувача {$session['user_id']}");
        }
    } catch (Exception $e) {
        sendMessage($chat_id, t('report_creation_error_general', $user_lang));
        logError("Exception в completeReport: " . $e->getMessage());
    }

    // Завершуємо сесію
    unset($report_sessions[$chat_id]);
}

function getStats() {
    global $db;
    return $db->getStats();
}

// Функція для обробки команди /cancel
function handleCancelCommand($chat_id, $lang, $message_text = "") {
    global $report_sessions, $admin_action_sessions, $broadcast_sessions;

    // Перевіряємо, чи є повідомлення командою /cancel (з юзернеймом бота або без)
    $is_cancel_command = false;
    if ($message_text === "/cancel") {
        $is_cancel_command = true;
    } else {
        // Перевіряємо команди виду /cancel@username_bot
        global $botUsername;
        if (strpos($message_text, "/cancel@") === 0) {
            $command_parts = explode('@', $message_text);
            if (count($command_parts) === 2 && $command_parts[1] === $botUsername) {
                $is_cancel_command = true;
            }
        }
    }

    // Якщо функцію викликали без тексту (з інших місць), вважаємо що це команда cancel
    if (empty($message_text)) {
        $is_cancel_command = true;
    }

    if (!$is_cancel_command) {
        return; // Це не команда скасування
    }

    $cancelled_something = false;
    $cancel_message = "";

    // Перевіряємо сесію репорту
    if (isset($report_sessions[$chat_id])) {
        unset($report_sessions[$chat_id]);
        $cancel_message = "<b>❌ " . t('report_creation_cancelled', $lang) . "</b>";
        $cancelled_something = true;
        logInfo("Створення репорту скасовано для $chat_id");
    }

    // Перевіряємо адмін-сесію (обробка репортів)
    if (isset($admin_action_sessions[$chat_id])) {
        // Видаляємо повідомлення з інструкціями якщо є
        if (isset($admin_action_sessions[$chat_id]['instruction_message_id'])) {
            deleteMessage($chat_id, $admin_action_sessions[$chat_id]['instruction_message_id']);
        }
        // Видаляємо повідомлення "Оберіть дію" якщо є
        if (isset($admin_action_sessions[$chat_id]['action_message_id'])) {
            deleteMessage($chat_id, $admin_action_sessions[$chat_id]['action_message_id']);
        }
        unset($admin_action_sessions[$chat_id]);
        $cancel_message = "<b>❌ " . t('admin_action_cancelled', $lang) . "</b>";
        $cancelled_something = true;
        logInfo("Адмін-дію скасовано для $chat_id");
    }

    // Перевіряємо сесію розсилки
    if (isset($broadcast_sessions[$chat_id])) {
        unset($broadcast_sessions[$chat_id]);
        $cancel_message = "<b>❌ " . t('broadcast_cancelled', $lang) . "</b>";
        $cancelled_something = true;
        logInfo("Розсилку скасовано для $chat_id");
    }

    // Відправляємо відповідне повідомлення
    if ($cancelled_something) {
        sendMessage($chat_id, $cancel_message);
    } else {
        sendMessage($chat_id, "ℹ️ " . t('nothing_to_cancel', $lang));
    }
}
?>