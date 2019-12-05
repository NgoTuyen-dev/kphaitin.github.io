<?php
/*
 * JohnCMS NEXT Mobile Content Management System (http://johncms.com)
 *
 * For copyright and license information, please see the LICENSE.md
 * Installing the system or redistributions of files must retain the above copyright notice.
 *
 * @link        http://johncms.com JohnCMS Project
 * @copyright   Copyright (C) JohnCMS Community
 * @license     GPL-3
 */

define('_IN_JOHNCMS', 1);

require('../system/bootstrap.php');

$id = isset($_REQUEST['id']) ? abs(intval($_REQUEST['id'])) : 0;
$act = isset($_GET['act']) ? trim($_GET['act']) : '';
$mod = isset($_GET['mod']) ? trim($_GET['mod']) : '';
$do = isset($_REQUEST['do']) ? trim($_REQUEST['do']) : false;

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Johncms\Api\UserInterface $systemUser */
$systemUser = $container->get(Johncms\Api\UserInterface::class);

/** @var Johncms\Api\ToolsInterface $tools */
$tools = $container->get(Johncms\Api\ToolsInterface::class);

/** @var Johncms\Api\ConfigInterface $config */
$config = $container->get(Johncms\Api\ConfigInterface::class);

/** @var Johncms\Counters $counters */
$counters = App::getContainer()->get('counters');

/** @var Zend\I18n\Translator\Translator $translator */
$translator = $container->get(Zend\I18n\Translator\Translator::class);
$translator->addTranslationFilePattern('gettext', __DIR__ . '/locale', '/%s/default.mo');

if (isset($_SESSION['ref'])) {
    unset($_SESSION['ref']);
}

// Настройки форума
$set_forum = $systemUser->isValid() ? unserialize($systemUser->set_forum) : [
    'farea'    => 0,
    'upfp'     => 0,
    'preview'  => 1,
    'postclip' => 1,
    'postcut'  => 2,
];

// Список расширений файлов, разрешенных к выгрузке

// Файлы архивов
$ext_arch = [
    'zip',
    'rar',
    '7z',
    'tar',
    'gz',
    'apk',
];
// Звуковые файлы
$ext_audio = [
    'mp3',
    'amr',
];
// Файлы документов и тексты
$ext_doc = [
    'txt',
    'pdf',
    'doc',
    'docx',
    'rtf',
    'djvu',
    'xls',
    'xlsx',
];
// Файлы Java
$ext_java = [
    'sis',
    'sisx',
    'apk',
];
// Файлы картинок
$ext_pic = [
    'jpg',
    'jpeg',
    'gif',
    'png',
    'bmp',
];
// Файлы SIS
$ext_sis = [
    'sis',
    'sisx',
];
// Файлы видео
$ext_video = [
    '3gp',
    'avi',
    'flv',
    'mpeg',
    'mp4',
];
// Файлы Windows
$ext_win = [
    'exe',
    'msi',
];
// Другие типы файлов (что не перечислены выше)
$ext_other = ['wmf'];

// Ограничиваем доступ к Форуму
$error = '';

if (!$config->mod_forum && $systemUser->rights < 7) {
    $error = _t('Forum is closed');
} elseif ($config->mod_forum == 1 && !$systemUser->isValid()) {
    $error = _t('For registered users only');
}

if ($error) {
    require('../system/head.php');
    echo '<div class="rmenu"><p>' . $error . '</p></div>';
    require('../system/end.php');
    exit;
}
$show_type = $_REQUEST['type'] ?? 'section';




// Переключаем режимы работы
$mods = [
    'addfile',
    'addvote',
    'close',
    'deltema',
    'delvote',
    'editpost',
    'editvote',
    'file',
    'files',
    'filter',
    'loadtem',
    'massdel',
    'new',
    'nt',
    'per',
    'show_post',
    'ren',
    'restore',
    'say',
    'tema',
    'users',
    'vip',
    'vote',
    'who',
    'curators',
];

if ($act && ($key = array_search($act, $mods)) !== false && file_exists('includes/' . $mods[$key] . '.php')) {
    require('includes/' . $mods[$key] . '.php');
} else {

    // Заголовки страниц форума
    if (empty($id)) {
        $headmod = 'forum';
        $textl = _t('Forum');
    } else {

        // Фиксируем местоположение и получаем заголовок страницы
        switch ($show_type) {
            case 'section':
                $headmod = 'forum,' . $id .',section';
                $res = $db->query("SELECT `name` FROM `forum_sections` WHERE `id`= " . $id)->fetch();
                break;

            case 'topics':
                $headmod = 'forum,' . $id .',topics';
                $res = $db->query("SELECT `name` FROM `forum_sections` WHERE `id`= " . $id)->fetch();
                break;

            case 'topic':
                $headmod = 'forum,' . $id .',topic';
                $res = $db->query("SELECT `name` FROM `forum_topic` WHERE `id`= " . $id)->fetch();
                break;

            default:
                $headmod = 'forum';
        }

        $hdr = preg_replace('#\[c\](.*?)\[/c\]#si', '', $res['name']);
        $hdr = strtr($hdr, [
            '&laquo;' => '',
            '&raquo;' => '',
            '&quot;'  => '',
            '&amp;'   => '',
            '&lt;'    => '',
            '&gt;'    => '',
            '&#039;'  => '',
        ]);
        $hdr = mb_substr($hdr, 0, 30);
        $hdr = $tools->checkout($hdr, 2, 2);
        $textl = empty($hdr) ? _t('Forum') : $hdr;
    }


    // Редирект на новые адреса страниц
    if(!empty($id)) {
        $check_section = $db->query("SELECT * FROM `forum_sections` WHERE `id`= '$id'");
        if (!$check_section->rowCount() && (empty($_REQUEST['type']) || (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'post'))) {
            $check_link = $db->query("SELECT * FROM `forum_redirects` WHERE `old_id`= '$id'")->fetch();
            if(!empty($check_link)) {
                http_response_code(301);
                header('Location: '.$check_link['new_link']);
                exit;
            }
        }
    }



    require('../system/head.php');

    // Если форум закрыт, то для Админов выводим напоминание
    if (!$config->mod_forum) {
        echo '<div class="alarm">' . _t('Forum is closed') . '</div>';
    } elseif ($config->mod_forum == 3) {
        echo '<div class="rmenu">' . _t('Read only') . '</div>';
    }

    if (!$systemUser->isValid()) {
        if (isset($_GET['newup'])) {
            $_SESSION['uppost'] = 1;
        }

        if (isset($_GET['newdown'])) {
            $_SESSION['uppost'] = 0;
        }
    }

    if ($id) {


        // Определяем тип запроса (каталог, или тема)
        if($show_type == 'topic') {
            $type = $db->query("SELECT * FROM `forum_topic` WHERE `id`= '$id'");
        } else {
            $type = $db->query("SELECT * FROM `forum_sections` WHERE `id`= '$id'");
        }



        if (!$type->rowCount()) {
            // Если темы не существует, показываем ошибку
            echo $tools->displayError(_t('Topic has been deleted or does not exists'), '<a href="index.php">' . _t('Forum') . '</a>');
            require('../system/end.php');
            exit;
        }

        $type1 = $type->fetch();

        // Фиксация факта прочтения Топика
        if ($systemUser->isValid() && $show_type == 'topic') {
            $db->query("INSERT INTO `cms_forum_rdm` (topic_id,  user_id, `time`)
                VALUES ('$id', '" . $systemUser->id . "', '" . time() . "')
                ON DUPLICATE KEY UPDATE `time` = VALUES(`time`)");
        }

        // Получаем структуру форума
        $res = true;
        $allow = 0;

        $parent = $show_type == 'topic' ? $type1['section_id'] : $type1['parent'];

        while (!empty($parent) && $res != false) {
            $res = $db->query("SELECT * FROM `forum_sections` WHERE `id` = '$parent' LIMIT 1")->fetch();

            $tree[] = '<a href="index.php?'. ($res['section_type'] == 1 ? 'type=topics&amp;' : '') .'id=' . $parent . '">' . $res['name'] . '</a>';

            /*if ($res['type'] == 'r' && !empty($res['edit'])) {
                $allow = intval($res['edit']);
            }*/

            $parent = $res['parent'];
        }

        $tree[] = '<a href="index.php">' . _t('Forum') . '</a>';
        krsort($tree);

        $tree[] = '<b>' . $type1['name'] . '</b>';


        // Счетчик файлов и ссылка на них
        $sql = ($systemUser->rights == 9) ? "" : " AND `del` != '1'";

        if ($show_type == 'topic') {
            $count = $db->query("SELECT COUNT(*) FROM `cms_forum_files` WHERE `topic` = '$id'" . $sql)->fetchColumn();

            if ($count > 0) {
                $filelink = '<a href="index.php?act=files&amp;t=' . $id . '">' . _t('Topic Files') . '</a>';
            }
        } elseif ($type1['section_type'] == 0) {
            $count = $db->query("SELECT COUNT(*) FROM `cms_forum_files` WHERE `cat` = ". $type1['id'] . $sql)->fetchColumn();

            if ($count > 0) {
                $filelink = '<a href="index.php?act=files&amp;c=' . $id . '">' . _t('Category Files') . '</a>';
            }
        } elseif ($type1['section_type'] == 1) {
            $count = $db->query("SELECT COUNT(*) FROM `cms_forum_files` WHERE `subcat` = '$id'" . $sql)->fetchColumn();

            if ($count > 0) {
                $filelink = '<a href="index.php?act=files&amp;s=' . $id . '">' . _t('Section Files') . '</a>';
            }
        }

        $filelink = isset($filelink) ? $filelink . '&#160;<span class="red">(' . $count . ')</span>' : false;

        // Счетчик "Кто в теме?"
        $wholink = false;

        if ($systemUser->isValid() && $show_type == 'topic') {
            $online = $db->query("SELECT (
SELECT COUNT(*) FROM `users` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE 'forum,$id,topic') AS online_u, (
SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE 'forum,$id,topic') AS online_g")->fetch();
            $wholink = '<a href="index.php?act=who&amp;id=' . $id . '">' . _t('Who is here') . '?</a>&#160;<span class="red">(' . $online['online_u'] . '&#160;/&#160;' . $online['online_g'] . ')</span>';
        }


        //             <div class="col p-0">
        //                 <ol class="breadcrumb">
        //                     <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a>
        //                     </li>
        //                     <li class="breadcrumb-item active">Dashboard</li>
        //                 </ol>
        //             </div>
        //         </div>

        // Выводим верхнюю панель навигации
        // print_r($tree);
        echo '<div class="row page-titles">
        <div class="col-md-3 p-0">
            <h4>Hello, <span>Welcome here</span></h4>
        </div>';
        echo'<div class="col p-0">';
        // echo '<a id="up"></a><p>' . $counters->forumNew(1) . '</p>' .
        echo '<nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">' . implode('</li><li class="breadcrumb-item active">', $tree) . 
            '</li></ol>
        </nav></div>' ;
        echo '</div>';
        // '<div class="topmenu"><a href="search.php?id=' . $id . '">' . _t('Search') . '</a>' . ($filelink ? ' | ' . $filelink : '') . ($wholink ? ' | ' . $wholink : '') .'</div>';

        switch ($show_type) {
            case 'section':
                ////////////////////////////////////////////////////////////
                // Список разделов форума                                 //
                ////////////////////////////////////////////////////////////
                $req = $db->query("SELECT * FROM `forum_sections` WHERE `parent`='$id' ORDER BY `sort`");
                $total = $req->rowCount();

                if ($total) {
                    $i = 0;

                    while ($res = $req->fetch()) {
                        echo $i % 2 ? '<div class="list2">' : '<div class="list1">';

                        if ($res['section_type'] == 1) {
                            $coltem = $db->query("SELECT COUNT(*) FROM `forum_topic` WHERE `section_id` = '".$res['id']."'" . ($systemUser->rights >= 7 ? '' : " AND (`deleted` != '1' OR deleted IS NULL)"))->fetchColumn();
                        } else {
                            $coltem = $db->query("SELECT COUNT(*) FROM `forum_sections` WHERE `parent` = '".$res['id']."'")->fetchColumn();
                        }

                        echo '<a href="?'. ($res['section_type'] == 1 ? 'type=topics&amp;' : '') .'id=' . $res['id'] . '">' . $res['name'] . '</a>';

                        if ($coltem) {
                            echo " [$coltem]";
                        }

                        if (!empty($res['description'])) {
                            echo '<div class="sub"><span class="gray">' . $res['description'] . '</span></div>';
                        }

                        echo '</div>';
                        ++$i;
                    }

                    unset($_SESSION['fsort_id']);
                    unset($_SESSION['fsort_users']);
                } else {
                    echo '<div class="menu"><p>' . _t('There are no sections in this category') . '</p></div>';
                }

                echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';
                break;

            case 'topics':
                ////////////////////////////////////////////////////////////
                // Список топиков                                         //
                ////////////////////////////////////////////////////////////
                $total = $db->query("SELECT COUNT(*) FROM `forum_topic` WHERE `section_id` = '$id'" . ($systemUser->rights >= 7 ? '' : " AND (`deleted` != '1' OR deleted IS NULL)"))->fetchColumn();

                if (($systemUser->isValid() && !isset($systemUser->ban['1']) && !isset($systemUser->ban['11']) && $config->mod_forum != 4) || $systemUser->rights) {
                    // Кнопка создания новой темы
                    echo '<div class="gmenu"><form action="index.php?act=nt&amp;id=' . $id . '" method="post"><input type="submit" value="' . _t('New Topic') . '" /></form></div>';
                }
                
                if ($total) {
                    $req = $db->query("SELECT tpc.*, (
SELECT COUNT(*) FROM `cms_forum_rdm` WHERE `time` >= tpc.last_post_date AND `topic_id` = tpc.id AND `user_id` = " . $systemUser->id . ") as `np`
FROM `forum_topic` tpc WHERE `section_id` = '$id'" . ($systemUser->rights >= 7 ? '' : " AND (`deleted` <> '1' OR deleted IS NULL)") . "
ORDER BY `pinned` DESC, `last_post_date` DESC LIMIT $start, $kmess");
                    $i = 0;

                    while ($res = $req->fetch()) {
                        if ($res['deleted']) {
                            echo '<div class="rmenu">';
                        } else {
                            echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
                        }

                        // Значки
                        $icons = [
                            ($res['np'] ? (!$res['pinned'] ? $tools->image('op.gif') : '') : $tools->image('np.gif')),
                            ($res['pinned'] ? $tools->image('pt.gif') : ''),
                            ($res['has_poll'] ? $tools->image('rate.gif') : ''),
                            ($res['closed'] ? $tools->image('tz.gif') : ''),
                        ];
                        echo implode('', array_filter($icons));

                        $post_count = $systemUser->rights >= 7 ? $res['mod_post_count'] : $res['post_count'];
                        echo '<a href="index.php?type=topic&amp;id=' . $res['id'] . '">' . (empty($res['name']) ? '-----' : $res['name']) . '</a> [' . $post_count . ']';

                        $cpg = ceil($post_count / $kmess);
                        if ($cpg > 1) {
                            echo '<a href="index.php?type=topic&amp;id=' . $res['id'] . '&amp;page=' . $cpg . '">&#160;&gt;&gt;</a>';
                        }

                        echo '<div class="sub">';
                        echo $res['user_name'];

                        $last_author = $systemUser->rights >= 7 ? $res['mod_last_post_author_name'] : $res['last_post_author_name'];

                        if (!empty($last_author)) {
                            echo '&#160;/&#160;' . $last_author;
                        }

                        $last_post_date = $systemUser->rights >= 7 ? $res['mod_last_post_date'] : $res['last_post_date'];

                        echo ' <span class="gray">(' . $tools->displayDate($last_post_date) . ')</span></div></div>';
                        ++$i;
                    }
                    unset($_SESSION['fsort_id']);
                    unset($_SESSION['fsort_users']);
                } else {
                    echo '<div class="menu"><p>' . _t('No topics in this section') . '</p></div>';
                }

                echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

                if ($total > $kmess) {
                    echo '<div class="topmenu">' . $tools->displayPagination('index.php?type=topics&id=' . $id . '&amp;', $start, $total, $kmess) . '</div>' .
                        '<p><form action="index.php?type=topics&id=' . $id . '" method="post">' .
                        '<input type="text" name="page" size="2"/>' .
                        '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
                        '</form></p>';
                }
                break;


            case 'topic':
                ////////////////////////////////////////////////////////////
                // Показываем тему с постами                              //
                ////////////////////////////////////////////////////////////
                $filter = isset($_SESSION['fsort_id']) && $_SESSION['fsort_id'] == $id ? 1 : 0;
                $sql = '';

                if ($filter && !empty($_SESSION['fsort_users'])) {
                    // Подготавливаем запрос на фильтрацию юзеров
                    $sw = 0;
                    $sql = ' AND (';
                    $fsort_users = unserialize($_SESSION['fsort_users']);

                    foreach ($fsort_users as $val) {
                        if ($sw) {
                            $sql .= ' OR ';
                        }

                        $sortid = intval($val);
                        $sql .= "`forum_messages`.`user_id` = '$sortid'";
                        $sw = 1;
                    }
                    $sql .= ')';
                }

                // Если тема помечена для удаления, разрешаем доступ только администрации
                if ($systemUser->rights < 6 && $type1['deleted'] == 1) {
                    echo '<div class="rmenu"><p>' . _t('Topic deleted') . '<br><a href="?type=topics&amp;id=' . $type1['section_id'] . '">' . _t('Go to Section') . '</a></p></div>';
                    require('../system/end.php');
                    exit;
                }

                $view_count = intval($type1['view_count']);
                // Фиксируем количество просмотров топика
                if (!empty($type1['id']) && (empty($_SESSION['viewed_topics']) || !in_array($type1['id'], $_SESSION['viewed_topics']))) {
                    $view_count = intval($type1['view_count']) + 1;
                    $db->query("UPDATE forum_topic SET view_count = ".$view_count." WHERE id = ".$type1['id']);
                    $_SESSION['viewed_topics'][] = $type1['id'];
                }


                // Счетчик постов темы
                $colmes = $db->query("SELECT COUNT(*) FROM `forum_messages` WHERE `topic_id`='$id'$sql" . ($systemUser->rights >= 7 ? '' : " AND (`deleted` != '1' OR `deleted` IS NULL)"))->fetchColumn();

                if ($start >= $colmes) {
                    // Исправляем запрос на несуществующую страницу
                    $start = max(0, $colmes - (($colmes % $kmess) == 0 ? $kmess : ($colmes % $kmess)));
                }

                if ($colmes > $kmess) {
                    echo '<div class="topmenu">' . $tools->displayPagination('index.php?type=topic&amp;id=' . $id . '&amp;', $start, $colmes, $kmess) . '</div>';
                }

                // Метка удаления темы
                if ($type1['deleted']) {
                    echo '<div class="rmenu">' . _t('Topic deleted by') . ': <b>' . $type1['deleted_by'] . '</b></div>';
                } elseif (!empty($type1['deleted_by']) && $systemUser->rights >= 7) {
                    echo '<div class="gmenu"><small>' . _t('Undelete topic') . ': <b>' . $type1['deleted_by'] . '</b></small></div>';
                }

                // Метка закрытия темы
                if ($type1['closed']) {
                    echo '<div class="rmenu">' . _t('Topic closed') . '</div>';
                }

                // Блок голосований
                if ($type1['has_poll']) {
                    $clip_forum = isset($_GET['clip']) ? '&amp;clip' : '';
                    $topic_vote = $db->query("SELECT `fvt`.`name`, `fvt`.`time`, `fvt`.`count`, (
SELECT COUNT(*) FROM `cms_forum_vote_users` WHERE `user`='" . $systemUser->id . "' AND `topic`='" . $id . "') as vote_user
FROM `cms_forum_vote` `fvt` WHERE `fvt`.`type`='1' AND `fvt`.`topic`='" . $id . "' LIMIT 1")->fetch();
                    echo '<div  class="gmenu"><b>' . $tools->checkout($topic_vote['name']) . '</b><br />';
                    $vote_result = $db->query("SELECT `id`, `name`, `count` FROM `cms_forum_vote` WHERE `type`='2' AND `topic`='" . $id . "' ORDER BY `id` ASC");

                    if (!$type1['closed'] && !isset($_GET['vote_result']) && $systemUser->isValid() && $topic_vote['vote_user'] == 0) {
                        // Выводим форму с опросами
                        echo '<form action="index.php?act=vote&amp;id=' . $id . '" method="post">';

                        while ($vote = $vote_result->fetch()) {
                            echo '<input type="radio" value="' . $vote['id'] . '" name="vote"/> ' . $tools->checkout($vote['name'], 0, 1) . '<br />';
                        }

                        echo '<p><input type="submit" name="submit" value="' . _t('Vote') . '"/><br /><a href="index.php?type=topic&amp;id=' . $id . '&amp;start=' . $start . '&amp;vote_result' . $clip_forum .
                            '">' . _t('Results') . '</a></p></form></div>';
                    } else {
                        // Выводим результаты голосования
                        ?>
                        <div class="vote-results">
                            <?php
                            while ($vote = $vote_result->fetch()) {
                                $count_vote = $topic_vote['count'] ? round(100 / $topic_vote['count'] * $vote['count']) : 0;
                                $color = '';
                                if($count_vote > 0 && $count_vote <= 25) {
                                    $color = 'progress-bg-green';
                                } elseif($count_vote > 25 && $count_vote <= 50) {
                                    $color = 'progress-bg-blue';
                                } elseif ($count_vote > 50 && $count_vote <= 75) {
                                    $color = 'progress-bg-yellow';
                                } elseif ($count_vote > 75 && $count_vote <= 100) {
                                    $color = 'progress-bg-red';
                                }
                                ?>
                                <div class="vote-name">
                                    <?php= ($tools->checkout($vote['name'], 0, 1) . ' [' . $vote['count'] . ']') ?>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?php= $color ?>" style="width: <?php=$count_vote ?>%"><?php= $count_vote ?>%</div>
                                </div>
                                <?php
                            }?>
                        </div>
                        <?php

                        echo '</div><div class="bmenu">' . _t('Total votes') . ': ';

                        if ($systemUser->rights > 6) {
                            echo '<a href="index.php?act=users&amp;id=' . $id . '">' . $topic_vote['count'] . '</a>';
                        } else {
                            echo $topic_vote['count'];
                        }

                        echo '</div>';

                        if ($systemUser->isValid() && $topic_vote['vote_user'] == 0) {
                            echo '<div class="bmenu"><a href="index.php?type=topic&amp;id=' . $id . '&amp;start=' . $start . $clip_forum . '">' . _t('Vote') . '</a></div>';
                        }
                    }
                }

                // Получаем данные о кураторах темы
                $curators = !empty($type1['curators']) ? unserialize($type1['curators']) : [];
                $curator = false;

                if ($systemUser->rights < 6 && $systemUser->rights != 3 && $systemUser->isValid()) {
                    if (array_key_exists($systemUser->id, $curators)) {
                        $curator = true;
                    }
                }

                // Фиксация первого поста в теме
                if (($set_forum['postclip'] == 2 && ($set_forum['upfp'] ? $start < (ceil($colmes - $kmess)) : $start > 0)) || isset($_GET['clip'])) {
                    $postres = $db->query("SELECT `forum_messages`.*, `users`.`sex`, `users`.`rights`, `users`.`lastdate`, `users`.`status`, `users`.`datereg`
                    FROM `forum_messages` LEFT JOIN `users` ON `forum_messages`.`user_id` = `users`.`id`
                    WHERE `forum_messages`.`topic_id` = '$id'" . ($systemUser->rights >= 7 ? "" : " AND (`forum_messages`.`deleted` != '1' OR `forum_messages`.`deleted` IS NULL)") . "
                    ORDER BY `forum_messages`.`id` LIMIT 1")->fetch();
                    echo '<div class="topmenu"><p>';

                    if ($systemUser->isValid() && $systemUser->id != $postres['user_id']) {
                        echo '<a href="../profile/?user=' . $postres['user_id'] . '&amp;fid=' . $postres['id'] . '"><b>' . $postres['user_name'] . '</b></a> ' .
                            '<a href="index.php?act=say&amp;id=' . $postres['id'] . '&amp;start=' . $start . '"> ' . _t('[r]') . '</a> ' .
                            '<a href="index.php?act=say&amp;id=' . $postres['id'] . '&amp;start=' . $start . '&amp;cyt"> ' . _t('[q]') . '</a> ';
                    } else {
                        echo '<b>' . $postres['user_name'] . '</b> ';
                    }

                    $user_rights = [
                        3 => '(FMod)',
                        6 => '(Smd)',
                        7 => '(Adm)',
                        9 => '(SV!)',
                    ];
                    echo @$user_rights[$postres['rights']];
                    echo(time() > $postres['lastdate'] + 300 ? '<span class="red"> [Off]</span>' : '<span class="green"> [ON]</span>');
                    echo ' <span class="gray">(' . $tools->displayDate($postres['date']) . ')</span><br>';

                    if ($postres['deleted']) {
                        echo '<span class="red">' . _t('Post deleted') . '</span><br>';
                    }

                    echo $tools->checkout(mb_substr($postres['text'], 0, 500), 0, 2);

                    if (mb_strlen($postres['text']) > 500) {
                        echo '...<a href="index.php?act=show_post&amp;id=' . $postres['id'] . '">' . _t('Read more') . '</a>';
                    }

                    echo '</p></div>';
                }

                // Памятка, что включен фильтр
                if ($filter) {
                    echo '<div class="rmenu">' . _t('Filter by author is activated') . '</div>';
                }

                // Задаем правила сортировки (новые внизу / вверху)
                if ($systemUser->isValid()) {
                    $order = $set_forum['upfp'] ? 'DESC' : 'ASC';
                } else {
                    $order = ((empty($_SESSION['uppost'])) || ($_SESSION['uppost'] == 0)) ? 'ASC' : 'DESC';
                }

                ////////////////////////////////////////////////////////////
                // Основной запрос в базу, получаем список постов темы    //
                ////////////////////////////////////////////////////////////
                $req = $db->query("
                  SELECT `forum_messages`.*, `users`.`sex`, `users`.`rights`, `users`.`lastdate`, `users`.`status`, `users`.`datereg`, (
                  SELECT COUNT(*) FROM `cms_forum_files` WHERE `post` = forum_messages.id) as file
                  FROM `forum_messages` LEFT JOIN `users` ON `forum_messages`.`user_id` = `users`.`id`
                  WHERE `forum_messages`.`topic_id` = '$id'"
                    . ($systemUser->rights >= 7 ? "" : " AND (`forum_messages`.`deleted` != '1' OR `forum_messages`.`deleted` IS NULL)") . "$sql
                  ORDER BY `forum_messages`.`id` $order LIMIT $start, $kmess
                ");

                // Верхнее поле "Написать"
                if (($systemUser->isValid() && !$type1['closed'] && $set_forum['upfp'] && $config->mod_forum != 3 && $allow != 4) || ($systemUser->rights >= 7 && $set_forum['upfp'])) {
                    echo '<div class="gmenu"><form name="form1" action="index.php?act=say&amp;id=' . $id . '" method="post">';

                    if ($set_forum['farea']) {
                        $token = mt_rand(1000, 100000);
                        $_SESSION['token'] = $token;
                        echo '<p>' .
                            $container->get(Johncms\Api\BbcodeInterface::class)->buttons('form1', 'msg') .
                            '<textarea rows="' . $systemUser->getConfig()->fieldHeight . '" name="msg"></textarea></p>' .
                            '<p><input type="checkbox" name="addfiles" value="1" /> ' . _t('Add File') .
                            '</p><p><input type="submit" name="submit" value="' . _t('Write') . '" style="width: 107px; cursor: pointer;"/> ' .
                            (isset($set_forum['preview']) && $set_forum['preview'] ? '<input type="submit" value="' . _t('Preview') . '" style="width: 107px; cursor: pointer;"/>' : '') .
                            '<input type="hidden" name="token" value="' . $token . '"/>' .
                            '</p></form></div>';
                    } else {
                        echo '<p><input type="submit" name="submit" value="' . _t('Write') . '"/></p></form></div>';
                    }
                }

                // Для администрации включаем форму массового удаления постов
                if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
                    echo '<form action="index.php?act=massdel" method="post">';
                }
                $i = 1;

                
                ////////////////////////////////////////////////////////////
                // Основной список постов                                 //
                ////////////////////////////////////////////////////////////
                while ($res = $req->fetch()) {
                    if($i == 1) {
                        $text = $res['text'];
                        $text = $tools->checkout($text, 1, 1);
                        $text = $tools->smilies($text, $res['rights'] ? 1 : 0);

                        ?>
                        <div class="row">
                            <div class="col-xl-8">
                                <div class="card">
                                    <div class="card-body">                            
                                        <div class="row">
                                            <?php
                                                echo $text;
                                            ?>                                            
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                                $demo_img = "https://image.winudf.com/v2/image1/Y29tLmxpZmUzNjAuYW5kcm9pZC5zYWZldHltYXBkX2ljb25fMTU1NDkzOTE5MV8wMjI/icon.png?w=170&fakeurl=1";
                            ?>

                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title">Game tương tự</h4>
                                        <div class="media border-bottom-1 p-t-15 p-b-15">
                                            <img src="<?php echo $demo_img; ?>" width="50px" class="mr-3 rounded-circle">
                                            <div class="media-body">
                                                <h5>Ai nạp nhiều hơn?</h5>
                                                <p class="m-b-0">Người nào nạp tiền nhiều nhất sẽ thắng</p>
                                            </div><span class="text-muted f-s-12">April 24, 2018</span>
                                        </div>
                                        <div class="media border-bottom-1 p-t-15 p-b-15">
                                            <img src="<?php echo $demo_img; ?>" width="50px" class="mr-3 rounded-circle">
                                            <div class="media-body">
                                                <h5>Ai nạp nhiều hơn?</h5>
                                                <p class="m-b-0">Người nào nạp tiền nhiều nhất sẽ thắng</p>
                                            </div><span class="text-muted f-s-12">April 24, 2018</span>
                                        </div>
                                        <div class="media p-t-15 p-b-15">
                                            <img src="<?php echo $demo_img; ?>" width="50px" class="mr-3 rounded-circle">
                                            <div class="media-body">
                                                <h5>Ai nạp nhiều hơn?</h5>
                                                <p class="m-b-0">Người nào nạp tiền nhiều nhất sẽ thắng</p>
                                            </div><span class="text-muted f-s-12">April 24, 2018</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }


                    // Если пост редактировался, показываем кем и когда
                    if ($res['edit_count']) {
                        echo '<br /><span class="gray"><small>' . _t('Edited') . ' <b>' . $res['editor_name'] . '</b> (' . $tools->displayDate($res['edit_time']) . ') <b>[' . $res['edit_count'] . ']</b></small></span>';
                    }

                    // Задаем права на редактирование постов
                    if (
                        (($systemUser->rights == 3 || $systemUser->rights >= 6 || $curator) && $systemUser->rights >= $res['rights'])
                        || ($res['user_id'] == $systemUser->id && !$set_forum['upfp'] && ($start + $i) == $colmes && $res['date'] > time() - 300)
                        || ($res['user_id'] == $systemUser->id && $set_forum['upfp'] && $start == 0 && $i == 1 && $res['date'] > time() - 300)
                        || ($i == 1 && $allow == 2 && $res['user_id'] == $systemUser->id)
                    ) {
                        $allowEdit = true;
                    } else {
                        $allowEdit = false;
                    }

                    // Если есть прикрепленные файлы, выводим их
                    if ($res['file']) {
                        $freq = $db->query("SELECT * FROM `cms_forum_files` WHERE `post` = '" . $res['id'] . "'");

                        echo '<div class="post-files">';
                        while ($fres = $freq->fetch()) {
                            $fls = round(@filesize('../files/forum/attach/' . $fres['filename']) / 1024, 2);
                            echo '<div class="gray" style="font-size: x-small;background-color: rgba(128, 128, 128, 0.1);padding: 2px 4px;float: left;margin: 4px 4px 0 0;">' . _t('Attachment') . ':';
                            // Предпросмотр изображений
                            $att_ext = strtolower(pathinfo('./files/forum/attach/' . $fres['filename'], PATHINFO_EXTENSION));
                            $pic_ext = [
                                'gif',
                                'jpg',
                                'jpeg',
                                'png',
                            ];

                            if (in_array($att_ext, $pic_ext)) {
                                echo '<div><a class="image-preview" title="'.$fres['filename'].'" data-source="index.php?act=file&amp;id=' . $fres['id'] . '" href="index.php?act=file&amp;id=' . $fres['id'] . '">';
                                echo '<img src="thumbinal.php?file=' . (urlencode($fres['filename'])) . '" alt="' . _t('Click to view image') . '" /></a></div>';
                            } else {
                                echo '<br><a href="index.php?act=file&amp;id=' . $fres['id'] . '">' . $fres['filename'] . '</a>';
                            }

                            echo ' (' . $fls . ' кб.)<br>';
                            echo _t('Downloads') . ': ' . $fres['dlcount'] . ' ' . _t('Time');

                            if ($allowEdit) {
                                echo '<br><a href="?act=editpost&amp;do=delfile&amp;fid=' . $fres['id'] . '&amp;id=' . $res['id'] . '">' . _t('Delete') . '</a>';
                            }

                            echo '</div>';
                            $file_id = $fres['id'];
                        }
                        echo '<div style="clear: both;"></div></div>';
                    }

                    // Ссылки на редактирование / удаление постов
                    if ($allowEdit) {
                        echo '<div class="sub">';

                        // Чекбокс массового удаления постов
                        if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
                            echo '<input type="checkbox" name="delch[]" value="' . $res['id'] . '"/>&#160;';
                        }

                        // Служебное меню поста
                        $menu = [
                            '<a href="index.php?act=editpost&amp;id=' . $res['id'] . '">' . _t('Edit') . '</a>',
                            ($systemUser->rights >= 7 && $res['deleted'] == 1 ? '<a href="index.php?act=editpost&amp;do=restore&amp;id=' . $res['id'] . '">' . _t('Restore') . '</a>' : ''),
                            ($res['deleted'] == 1 ? '' : '<a href="index.php?act=editpost&amp;do=del&amp;id=' . $res['id'] . '">' . _t('Delete') . '</a>'),
                        ];
                        echo implode(' | ', array_filter($menu));

                        // Показываем, кто удалил пост
                        if ($res['deleted']) {
                            echo '<div class="red">' . _t('Post deleted') . ': <b>' . $res['deleted_by'] . '</b></div>';
                        } elseif (!empty($res['deleted_by'])) {
                            echo '<div class="green">' . _t('Post restored by') . ': <b>' . $res['deleted_by'] . '</b></div>';
                        }

                        // Показываем IP и Useragent
                        if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
                            if ($res['ip_via_proxy']) {
                                echo '<div class="gray"><b class="red"><a href="' . $config->homeurl . '/admin/index.php?act=search_ip&amp;ip=' . long2ip($res['ip']) . '">' . long2ip($res['ip']) . '</a></b> - ' .
                                    '<a href="' . $config->homeurl . '/admin/index.php?act=search_ip&amp;ip=' . long2ip($res['ip_via_proxy']) . '">' . long2ip($res['ip_via_proxy']) . '</a>' .
                                    ' - ' . $res['user_agent'] . '</div>';
                            } else {
                                echo '<div class="gray"><a href="' . $config->homeurl . '/admin/index.php?act=search_ip&amp;ip=' . long2ip($res['ip']) . '">' . long2ip($res['ip']) . '</a> - ' . $res['user_agent'] . '</div>';
                            }
                        }

                        echo '</div>';
                    }
                    ++$i;
                }

                // Кнопка массового удаления постов
                if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
                    echo '<div class="rmenu"><input type="submit" value=" ' . _t('Delete') . ' "/></div>';
                    echo '</form>';
                }

                // Нижнее поле "Написать"
                if (($systemUser->isValid() && !$type1['closed'] && !$set_forum['upfp'] && $config->mod_forum != 3 && $allow != 4) || ($systemUser->rights >= 7 && !$set_forum['upfp'])) {
                    echo '<div class="gmenu"><form name="form2" action="index.php?act=say&amp;type=post&amp;id=' . $id . '" method="post">';

                    if ($set_forum['farea']) {
                        $token = mt_rand(1000, 100000);
                        $_SESSION['token'] = $token;
                        echo '<p>';
                        echo $container->get(Johncms\Api\BbcodeInterface::class)->buttons('form2', 'msg');
                        echo '<textarea rows="' . $systemUser->getConfig()->fieldHeight . '" name="msg"></textarea><br></p>' .
                            '<p><input type="checkbox" name="addfiles" value="1" /> ' . _t('Add File');

                        echo '</p><p><input type="submit" name="submit" value="' . _t('Write') . '" style="width: 107px; cursor: pointer;"/> ' .
                            (isset($set_forum['preview']) && $set_forum['preview'] ? '<input type="submit" value="' . _t('Preview') . '" style="width: 107px; cursor: pointer;"/>' : '') .
                            '<input type="hidden" name="token" value="' . $token . '"/>' .
                            '</p></form></div>';
                    } else {
                        echo '<p><input type="submit" name="submit" value="' . _t('Write') . '"/></p></form></div>';
                    }
                }

                echo '<div class="phdr"><a id="down"></a><a href="#up">' . $tools->image('up.png', ['class' => '']) . '</a>' .
                    '&#160;&#160;' . _t('Total') . ': ' . $colmes . '</div>';

                // Постраничная навигация
                if ($colmes > $kmess) {
                    echo '<div class="topmenu">' . $tools->displayPagination('index.php?type=topic&amp;id=' . $id . '&amp;', $start, $colmes, $kmess) . '</div>' .
                        '<p><form action="index.php?type=topic&amp;id=' . $id . '" method="post">' .
                        '<input type="text" name="page" size="2"/>' .
                        '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
                        '</form></p>';
                } else {
                    echo '<br />';
                }

                // Список кураторов
                if ($curators) {
                    $array = [];

                    foreach ($curators as $key => $value) {
                        $array[] = '<a href="../profile/?user=' . $key . '">' . $value . '</a>';
                    }

                    echo '<p><div class="func">' . _t('Curators') . ': ' . implode(', ', $array) . '</div></p>';
                }

                // Ссылки на модерские функции управления темой
                if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
                    echo '<p><div class="func">';

                    if ($systemUser->rights >= 7) {
                        echo '<a href="index.php?act=curators&amp;id=' . $id . '&amp;start=' . $start . '">' . _t('Curators of the Topic') . '</a><br />';
                    }

                    echo isset($topic_vote) && $topic_vote > 0
                        ? '<a href="index.php?act=editvote&amp;id=' . $id . '">' . _t('Edit Poll') . '</a><br><a href="index.php?act=delvote&amp;id=' . $id . '">' . _t('Delete Poll') . '</a><br>'
                        : '<a href="index.php?act=addvote&amp;id=' . $id . '">' . _t('Add Poll') . '</a><br>';
                    echo '<a href="index.php?act=ren&amp;id=' . $id . '">' . _t('Rename Topic') . '</a><br>';

                    // Закрыть - открыть тему
                    if ($type1['closed'] == 1) {
                        echo '<a href="index.php?act=close&amp;id=' . $id . '">' . _t('Open Topic') . '</a><br>';
                    } else {
                        echo '<a href="index.php?act=close&amp;id=' . $id . '&amp;closed">' . _t('Close Topic') . '</a><br>';
                    }

                    // Удалить - восстановить тему
                    if ($type1['deleted'] == 1) {
                        echo '<a href="index.php?act=restore&amp;id=' . $id . '">' . _t('Restore Topic') . '</a><br>';
                    }

                    echo '<a href="index.php?act=deltema&amp;id=' . $id . '">' . _t('Delete Topic') . '</a><br>';

                    if ($type1['pinned'] == 1) {
                        echo '<a href="index.php?act=vip&amp;id=' . $id . '">' . _t('Unfix Topic') . '</a>';
                    } else {
                        echo '<a href="index.php?act=vip&amp;id=' . $id . '&amp;vip">' . _t('Pin Topic') . '</a>';
                    }

                    echo '<br><a href="index.php?act=per&amp;id=' . $id . '">' . _t('Move Topic') . '</a></div></p>';
                }

                echo '<div>'._t('Views') . ': ' . $view_count .'</div>';

                // Ссылка на список "Кто в теме"
                if ($wholink) {
                    echo '<div>' . $wholink . '</div>';
                }

                // Ссылка на фильтр постов
                if ($filter) {
                    echo '<div><a href="index.php?act=filter&amp;id=' . $id . '&amp;do=unset">' . _t('Cancel Filter') . '</a></div>';
                } else {
                    echo '<div><a href="index.php?act=filter&amp;id=' . $id . '&amp;start=' . $start . '">' . _t('Filter by author') . '</a></div>';
                }

                // Ссылка на скачку темы
                echo '<a href="index.php?act=tema&amp;id=' . $id . '">' . _t('Download Topic') . '</a>';
                break;

            default:
                // Если неверные данные, показываем ошибку
                echo $tools->displayError(_t('Wrong data'));
                break;
        }
    } else {
        ////////////////////////////////////////////////////////////
        // Список Категорий форума                                //
        ////////////////////////////////////////////////////////////
        $count = $db->query("SELECT COUNT(*) FROM `cms_forum_files`" . ($systemUser->rights >= 7 ? '' : " WHERE `del` != '1'"))->fetchColumn();
        echo '<p>' . $counters->forumNew(1) . '</p>' .
            '<div class="phdr"><b>' . _t('Forum') . '</b></div>' .
            '<div class="topmenu"><a href="search.php">' . _t('Search') . '</a> | <a href="index.php?act=files">' . _t('Files') . '</a> <span class="red">(' . $count . ')</span></div>';
        $req = $db->query("SELECT sct.`id`, sct.`name`, sct.`description`, (
SELECT COUNT(*) FROM `forum_sections` WHERE `parent`=sct.id) as cnt
FROM `forum_sections` sct WHERE sct.parent IS NULL OR sct.parent = 0 ORDER BY sct.`sort`");
        $i = 0;

        while ($res = $req->fetch()) {
            echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
            echo '<a href="index.php?id=' . $res['id'] . '">' . $res['name'] . '</a> [' . $res['cnt'] . ']';

            if (!empty($res['description'])) {
                echo '<div class="sub"><span class="gray">' . $res['description'] . '</span></div>';
            }

            echo '</div>';
            ++$i;
        }
        $online = $db->query("SELECT (
SELECT COUNT(*) FROM `users` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE 'forum%') AS online_u, (
SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE 'forum%') AS online_g")->fetch();
        echo '<div class="phdr">' . ($systemUser->isValid() ? '<a href="index.php?act=who">' . _t('Who in Forum') . '</a>' : _t('Who in Forum')) . '&#160;(' . $online['online_u'] . '&#160;/&#160;' . $online['online_g'] . ')</div>';
        unset($_SESSION['fsort_id']);
        unset($_SESSION['fsort_users']);
    }

    // Навигация внизу страницы
    echo '<p>' . ($id ? '<a href="index.php">' . _t('Forum') . '</a><br />' : '');

    if (!$id) {
        echo '<a href="../help/?act=forum">' . _t('Forum rules') . '</a>';
    }

    echo '</p>';

    if (!$systemUser->isValid()) {
        if ((empty($_SESSION['uppost'])) || ($_SESSION['uppost'] == 0)) {
            echo '<a href="index.php?id=' . $id . '&amp;page=' . $page . '&amp;newup">' . _t('New at the top') . '</a>';
        } else {
            echo '<a href="index.php?id=' . $id . '&amp;page=' . $page . '&amp;newdown">' . _t('New at the bottom') . '</a>';
        }
    }
}

require_once('../system/end.php');
