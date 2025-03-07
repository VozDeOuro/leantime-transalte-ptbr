<?php

/**
 * Controller
 */

namespace Leantime\Domain\Goalcanvas\Controllers {

    use Leantime\Core\Controller;
    use Leantime\Core\Mailer;
    use Leantime\Domain\Canvas\Repositories\Canvas;
    use Leantime\Domain\Canvas\Service\Canvas as CanvasService;
    use Leantime\Domain\Queue\Repositories\Queue as QueueRepo;
    use Illuminate\Support\Str;

    class Dashboard extends Controller
    {
        /**
         * Constant that must be redefined
         */
        protected const CANVAS_NAME = 'goal';

        private \Leantime\Domain\Projects\Services\Projects $projectService;
        private \Leantime\Domain\Goalcanvas\Services\Goalcanvas $goalService;
        private object $canvasRepo;

        /**
         * init - initialize private variables
         */
        public function init(
            \Leantime\Domain\Projects\Services\Projects $projectService,
            \Leantime\Domain\Goalcanvas\Services\Goalcanvas $goalService
        ) {
            $this->projectService = $projectService;
            $this->goalService = $goalService;
            $canvasName = Str::studly(static::CANVAS_NAME) . 'canvas';
            $repoName = app()->getNamespace() . "Domain\\$canvasName\\Repositories\\$canvasName";
            $this->canvasRepo = app()->make($repoName);
        }

        /**
         * run - display template and edit data
         *
         * @access public
         */
        public function run()
        {
            $allCanvas = $this->canvasRepo->getAllCanvas($_SESSION['currentProject']);

            //Create default canvas.
            if ($allCanvas == false || count($allCanvas) == 0) {
                $values = [
                    'title' => $this->language->__("label.board"),
                    'author' => $_SESSION['userdata']['id'],
                    'projectId' => $_SESSION['currentProject'],
                ];
                $currentCanvasId = $this->canvasRepo->addCanvas($values);
                $allCanvas = $this->canvasRepo->getAllCanvas($_SESSION['currentProject']);
            }




            $goalAnalytics = array(
                "numCanvases" => $allCanvas ? count($allCanvas) : 0,
                "numGoals" => "0",
                "goalsOnTrack" => 0,
                "goalsAtRisk" => 0,
                "goalsMiss" => 0,
                "avgPercentComplete" => '0',
            );

            $totalPercent = 0;
            foreach ($allCanvas as $canvas) {
                $canvasItems = $this->canvasRepo->getCanvasItemsById($canvas["id"]);
                foreach ($canvasItems as $item) {
                    $goalAnalytics["numGoals"]++;

                    if ($item["status"] == 'status_ontrack') {
                        $goalAnalytics["goalsOnTrack"]++;
                    }

                    if ($item["status"] == 'status_atrisk') {
                        $goalAnalytics["goalsAtRisk"]++;
                    }

                    if ($item["status"] == 'status_miss') {
                        $goalAnalytics["goalsMiss"]++;
                    }

                    $total = $item['endValue'] - $item['startValue'];
                    $progressValue = $item['currentValue'] - $item['startValue'];

                    if ($total > 0) {
                        $percentDone = round($progressValue / $total * 100, 2);
                    } else {
                        $percentDone = 0;
                    }

                    $totalPercent = $totalPercent + $percentDone;
                }
            }

            if ($goalAnalytics["numGoals"] > 0) {
                $goalAnalytics["avgPercentComplete"] = $totalPercent / $goalAnalytics["numGoals"];
            }


            if (isset($_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'])) {
                $currentCanvasId = $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'];
                //Ensure canvas id is in the list of currentCanvases (could be old value after project select

                $found = false;
                foreach ($allCanvas as $row) {
                    if ($currentCanvasId == $row['id']) {
                        $found = true;
                        break;
                    }
                }

                if ($found == false) {
                    $currentCanvasId = -1;
                    $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = '';
                }
            } else {
                $currentCanvasId = -1;
                $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = '';
            }

            if (count($allCanvas) > 0 && $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] == '') {
                $currentCanvasId = $allCanvas[0]['id'];
                $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = $currentCanvasId;
            }

            if (isset($_GET['id']) === true) {
                $currentCanvasId = (int)$_GET['id'];
                $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = $currentCanvasId;
            }

            if (isset($_REQUEST['searchCanvas']) === true) {
                $currentCanvasId = (int)$_REQUEST['searchCanvas'];
                $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = $currentCanvasId;
                $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas/showCanvas/');
            }

            // Add Canvas
            if (isset($_POST['newCanvas'])) {
                if (isset($_POST['canvastitle']) && !empty($_POST['canvastitle'])) {
                    if (!$this->canvasRepo->existCanvas($_SESSION['currentProject'], $_POST['canvastitle'])) {
                        $values = [
                            'title' => $_POST['canvastitle'],
                            'author' => $_SESSION['userdata']['id'],
                            'projectId' => $_SESSION['currentProject'],
                        ];
                        $currentCanvasId = $this->canvasRepo->addCanvas($values);
                        $allCanvas = $this->canvasRepo->getAllCanvas($_SESSION['currentProject']);

                        $mailer = app()->make(Mailer::class);
                        $this->projectService = app()->make(\Leantime\Domain\Projects\Services\Projects::class);
                        $users = $this->projectService->getUsersToNotify($_SESSION['currentProject']);

                        $mailer->setSubject($this->language->__('notification.board_created'));

                        $actual_link = CURRENT_URL;
                        $message = sprintf(
                            $this->language->__('email_notifications.canvas_created_message'),
                            $_SESSION['userdata']['name'],
                            "<a href='" . $actual_link . "'>" . $values['title'] . '</a>'
                        );
                        $mailer->setHtml($message);

                        // New queuing messaging system
                        $queue = app()->make(QueueRepo::class);
                        $queue->queueMessageToUsers(
                            $users,
                            $message,
                            $this->language->__('notification.board_created'),
                            $_SESSION['currentProject']
                        );

                        $this->tpl->setNotification($this->language->__('notification.board_created'), 'success');

                        $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = $currentCanvasId;
                        $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas/showCanvas/');
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.board_exists'), 'error');
                    }
                } else {
                    $this->tpl->setNotification($this->language->__('notification.please_enter_title'), 'error');
                }
            }

            // Edit Canvas
            if (isset($_POST['editCanvas']) && $currentCanvasId > 0) {
                if (isset($_POST['canvastitle']) && !empty($_POST['canvastitle'])) {
                    if (!$this->canvasRepo->existCanvas($_SESSION['currentProject'], $_POST['canvastitle'])) {
                        $values = array('title' => $_POST['canvastitle'], 'id' => $currentCanvasId);
                        $currentCanvasId = $this->canvasRepo->updateCanvas($values);

                        $this->tpl->setNotification($this->language->__('notification.board_edited'), 'success');
                        $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas/showCanvas/');
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.board_exists'), 'error');
                    }
                } else {
                    $this->tpl->setNotification($this->language->__('notification.please_enter_title'), 'error');
                }
            }

            // Clone canvas
            if (isset($_POST['cloneCanvas']) && $currentCanvasId > 0) {
                if (isset($_POST['canvastitle']) && !empty($_POST['canvastitle'])) {
                    if (!$this->canvasRepo->existCanvas($_SESSION['currentProject'], $_POST['canvastitle'])) {
                        $currentCanvasId = $this->canvasRepo->copyCanvas(
                            $_SESSION['currentProject'],
                            $currentCanvasId,
                            $_SESSION['userdata']['id'],
                            $_POST['canvastitle']
                        );
                        $allCanvas = $this->canvasRepo->getAllCanvas($_SESSION['currentProject']);

                        $this->tpl->setNotification($this->language->__('notification.board_copied'), 'success');

                        $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = $currentCanvasId;
                        $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas/showCanvas/');
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.board_exists'), 'error');
                    }
                } else {
                    $this->tpl->setNotification($this->language->__('notification.please_enter_title'), 'error');
                }
            }

            // Merge canvas
            if (isset($_POST['mergeCanvas']) && $currentCanvasId > 0) {
                if (isset($_POST['canvasid']) && $_POST['canvasid'] > 0) {
                    $status = $this->canvasRepo->mergeCanvas($currentCanvasId, $_POST['canvasid']);

                    if ($status) {
                        $this->tpl->setNotification($this->language->__('notification.board_merged'), 'success');
                        $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas/showCanvas/');
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.merge_error'), 'error');
                    }
                } else {
                    $this->tpl->setNotification($this->language->__('notification.internal_error'), 'error');
                }
            }

            // Import canvas
            if (isset($_POST['importCanvas'])) {
                if (isset($_FILES['canvasfile']) && $_FILES['canvasfile']['error'] === 0) {
                    $uploadfile = tempnam(sys_get_temp_dir(), 'leantime.') . '.xml';

                    $status = move_uploaded_file($_FILES['canvasfile']['tmp_name'], $uploadfile);
                    if ($status) {
                        $services = app()->make(CanvasService::class);
                        $importCanvasId = $services->import(
                            $uploadfile,
                            static::CANVAS_NAME . 'canvas',
                            projectId: $_SESSION['currentProject'],
                            authorId: $_SESSION['userdata']['id']
                        );
                        unlink($uploadfile);

                        if ($importCanvasId !== false) {
                            $currentCanvasId = $importCanvasId;
                            $allCanvas = $this->canvasRepo->getAllCanvas($_SESSION['currentProject']);
                            $_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'] = $currentCanvasId;

                            $mailer = app()->make(Mailer::class);
                            $this->projectService = app()->make(\Leantime\Domain\Projects\Services\Projects::class);
                            $users = $this->projectService->getUsersToNotify($_SESSION['currentProject']);
                            $canvas = $this->canvasRepo->getSingleCanvas($currentCanvasId);
                            $mailer->setSubject($this->language->__('notification.board_imported'));

                            $actual_link = CURRENT_URL;
                            $message = sprintf(
                                $this->language->__('email_notifications.canvas_imported_message'),
                                $_SESSION['userdata']['name'],
                                "<a href='" . $actual_link . "'>" . $canvas[0]['title'] . '</a>'
                            );
                            $mailer->setHtml($message);

                            // New queuing messaging system
                            $queue = app()->make(QueueRepo::class);
                            $queue->queueMessageToUsers(
                                $users,
                                $message,
                                $this->language->__('notification.board_imported'),
                                $_SESSION['currentProject']
                            );

                            $this->tpl->setNotification($this->language->__('notification.board_imported'), 'success');
                            $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas/showCanvas/');
                        } else {
                            $this->tpl->setNotification($this->language->__('notification.board_import_failed'), 'error');
                        }
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.board_import_failed'), 'error');
                    }
                }
            }

            $this->tpl->assign('currentCanvas', $currentCanvasId);
            $this->tpl->assign('goalStats', $goalAnalytics);
            $this->tpl->assign('canvasIcon', $this->canvasRepo->getIcon());
            $this->tpl->assign('canvasTypes', $this->canvasRepo->getCanvasTypes());
            $this->tpl->assign('statusLabels', $this->canvasRepo->getStatusLabels());
            $this->tpl->assign('relatesLabels', $this->canvasRepo->getRelatesLabels());
            $this->tpl->assign('dataLabels', $this->canvasRepo->getDataLabels());
            $this->tpl->assign('disclaimer', $this->canvasRepo->getDisclaimer());
            $this->tpl->assign('allCanvas', $allCanvas);
            $this->tpl->assign('canvasItems', $this->goalService->getCanvasItemsById($currentCanvasId));
            $this->tpl->assign('users', $this->projectService->getUsersAssignedToProject($_SESSION['currentProject']));

            if (!isset($_GET['raw'])) {
                $this->tpl->display(static::CANVAS_NAME . 'canvas.dashboard');
            }
        }
    }



}
