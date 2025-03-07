<?php

namespace Leantime\Domain\Help\Controllers {

    use Exception;
    use Leantime\Core\Controller;
    use Leantime\Core\Theme;
    use Leantime\Domain\Auth\Models\Roles;
    use Leantime\Domain\Auth\Services\Auth;
    use Leantime\Domain\Projects\Services\Projects;
    class FirstLogin extends Controller
    {
        /**
         * get - handle get requests
         *
         * @access public
         *
         */
        public function get($params)
        {

            Auth::authOrRedirect([Roles::$owner, Roles::$admin, Roles::$manager]);

            $step = 1;
            if (isset($_GET['step']) && is_numeric($_GET['step'])) {
                $step = $_GET['step'];
            }

            $this->tpl->assign('currentStep', $step);
            $this->tpl->displayPartial("help.firstLoginDialog");
        }

        /**
         * post - handle post requests
         *
         * @access public
         *
         */
        public function post($params)
        {
            $settingsRepo = app()->make(\Leantime\Domain\Setting\Repositories\Setting::class);

            if (isset($_POST['step']) && $_POST['step'] == 1) {
                if (isset($_POST['projectname'])) {
                    $projectService = app()->make(Projects::class);
                    $projectService->patch($_SESSION['currentProject'], array("name" => $_POST['projectname']));
                    $projectService->changeCurrentSessionProject($_SESSION['currentProject']);
                }

                $settingsRepo->saveSetting("companysettings.completedOnboarding", true);

                $this->tpl->redirect(BASE_URL . "/help/firstLogin?step=2");
            }

            if (isset($_POST['step']) && $_POST['step'] == 2) {
                if (isset($_POST['theme'])) {
                    $postTheme = htmlentities($_POST['theme']);

                    $themeCore = app()->make(Theme::class);

                    //Only save if it is actually available.
                    //Should not be an issue unless some shenanigans is happening
                    try {
                        $themeCore->setActive($postTheme);
                        $settingsRepo->saveSetting(
                            "usersettings." . $_SESSION['userdata']['id'] . ".theme",
                            $postTheme
                        );
                    } catch (Exception $e) {
                        error_log($e);
                    }
                }
                $this->tpl->redirect(BASE_URL . "/help/firstLogin?step=3");
            }

            if (isset($_POST['step']) && $_POST['step'] == 3) {
                $userService = app()->make(\Leantime\Domain\Users\Services\Users::class);
                $projectsRepo = app()->make(\Leantime\Domain\Projects\Repositories\Projects::class);

                for ($i = 1; $i <= 3; $i++) {
                    if (isset($_POST['email' . $i]) && $_POST['email' . $i] != '') {
                        $values = array(
                            'firstname' => '',
                            'lastname' => '',
                            'user' => ($_POST['email' . $i]),
                            'phone' => '',
                            'role' => '20',
                            'password' => '',
                            'pwReset' => '',
                            'status' => '',
                            'clientId' => '',
                        );

                        if (filter_var($_POST['email' . $i], FILTER_VALIDATE_EMAIL)) {
                            if ($userService->usernameExist($_POST['email' . $i]) === false) {
                                $userId = $userService->createUserInvite($values);
                                $projectsRepo->editUserProjectRelations($userId, array($_SESSION['currentProject']));

                                $this->tpl->setNotification("notification.user_invited_successfully", 'success');
                            }
                        }
                    }
                }

                $this->tpl->redirect(BASE_URL . "/help/firstLogin?step=complete");
            }
        }

        /**
         * put - handle put requests
         *
         * @access public
         *
         */
        public function put($params)
        {
        }

        /**
         * delete - handle delete requests
         *
         * @access public
         *
         */
        public function delete($params)
        {
        }
    }

}
