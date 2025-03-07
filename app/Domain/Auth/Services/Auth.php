<?php

namespace Leantime\Domain\Auth\Services {

    use Exception;
    use Leantime\Domain\Auth\Models\Roles;
    use Leantime\Domain\Ldap\Services\Ldap;
    use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
    use Leantime\Domain\Auth\Repositories\Auth as AuthRepository;
    use Leantime\Domain\Users\Repositories\Users as UserRepository;
    use Leantime\Core\Environment as EnvironmentCore;
    use Leantime\Core\Language as LanguageCore;
    use Leantime\Core\Session as SessionCore;
    use Leantime\Core\Mailer as MailerCore;
    use Leantime\Core\Frontcontroller as FrontcontrollerCore;
    use Leantime\Core\Eventhelpers;
    use RobThree\Auth\TwoFactorAuth;

    class Auth
    {
        use Eventhelpers;

        /**
         * @access private
         * @var    integer user id from DB
         */
        private $userId = null;

        /**
         * @access private
         * @var    integer user id from DB
         */
        private $clientId = null;

        /**
         * @access private
         * @var    string username from db
         */
        private $username = null;

        /**
         * @access private
         * @var    string username from db
         */
        private $name = '';

        /**
         * @access private
         * @var    string profileid (image) from db
         */
        private $profileId = '';

        /**
         * @access private
         * @var    string
         */
        private $password = null;

        /**
         * @access private
         * @var    string username (emailaddress)
         */
        private $user = null;

        /**
         * @access private
         * @var    string username (emailaddress)
         */
        private $mail = null;

        /**
         * @access private
         * @var    boolean $twoFAEnabled
         */
        private $twoFAEnabled;

        /**
         * @access private
         * @var    string $twoFASecret
         */
        private $twoFASecret;

        /**
         * @access private
         * @var    string
         */
        private $session = null;

        /**
         * @access public
         * @var    string userrole (admin, client, employee)
         */
        public $role = '';
        public $settings = '';

        /**
         * @access public
         * @var    integer time for cookie
         */
        public $cookieTime;

        /**
         * @access public
         * @var    string
         */
        public $error = "";

        /**
         * @access public
         * @var    string
         */
        public $success = "";

        /**
         * @access public
         * @var    string
         */
        public $resetInProgress = false;

        /**
         * @access public
         * @var    object
         */
        public $hasher;

        /*
         * How often can a user reset a password before it has to be changed
         */
        public $pwResetLimit = 5;

        private EnvironmentCore $config;
        public LanguageCore $language;
        public SettingRepository $settingsRepo;
        public AuthRepository $authRepo;
        public UserRepository $userRepo;

        /**
         * __construct - getInstance of session and get sessionId and refers to login if post is set
         *
         * @param  $sessionid
         * @throws Exception
         */
        public function __construct(
            EnvironmentCore $config,
            SessionCore $session,
            LanguageCore $language,
            SettingRepository $settingsRepo,
            AuthRepository $authRepo,
            UserRepository $userRepo
        ) {
            $this->config = $config;
            $this->session = $session->getSID();
            $this->language = $language;
            $this->settingsRepo = $settingsRepo;
            $this->authRepo = $authRepo;
            $this->userRepo = $userRepo;

            $this->cookieTime = $this->config->sessionExpiration;
        }

        /**
         * @param boolean $forceGlobalRoleCheck
         * @return string|boolean returns role as string or false on failure
         */
        public static function getRoleToCheck(bool $forceGlobalRoleCheck): string|bool
        {
            if (isset($_SESSION['userdata']) === false) {
                return false;
            }

            if ($forceGlobalRoleCheck) {
                $roleToCheck = $_SESSION['userdata']['role'];
                //If projectRole is not defined or if it is set to inherited
            } elseif (!isset($_SESSION['userdata']['projectRole']) || $_SESSION['userdata']['projectRole'] == "inherited" || $_SESSION['userdata']['projectRole'] == "") {
                $roleToCheck = $_SESSION['userdata']['role'];
                //Do not overwrite admin or owner roles
            } elseif ($_SESSION['userdata']['role'] == Roles::$owner || $_SESSION['userdata']['role'] == Roles::$admin || $_SESSION['userdata']['role'] == Roles::$manager) {
                $roleToCheck = $_SESSION['userdata']['role'];
                //In all other cases check the project role
            } else {
                $roleToCheck = $_SESSION['userdata']['projectRole'];
            }

            //Ensure the role is a valid role
            if (in_array($roleToCheck, Roles::getRoles()) === false) {
                error_log("Check for invalid role detected: " . $roleToCheck);
                return false;
            }

            return $roleToCheck;
        }

        /**
         * login - Validate POST-data with DB
         *
         * @access private
         * @return boolean
         */
        public function login($username, $password)
        {

            self::dispatch_event("beforeLoginCheck", ['username' => $username, 'password' => $password]);

            //different identity providers can live here
            //they all need to
            ////A: ensure the user is in leantime (with a valid role) and if not create the user
            ////B: set the session variables
            ////C: update users from the identity provider
            //Try Ldap
            if ($this->config->useLdap === true && extension_loaded('ldap')) {
                $ldap = app()->make(Ldap::class);

                if ($ldap->connect() && $ldap->bind($username, $password)) {
                    //Update username to include domain
                    $usernameWDomain = $ldap->getEmail($username);
                    //Get user
                    $user = $this->userRepo->getUserByEmail($usernameWDomain);

                    $ldapUser = $ldap->getSingleUser($username);

                    if ($ldapUser === false) {
                        return false;
                    }

                    //If user does not exist create user
                    if ($user == false) {
                        $userArray = array(
                            'firstname' => $ldapUser['firstname'],
                            'lastname' => $ldapUser['lastname'],
                            'phone' => $ldapUser['phone'],
                            'user' => $ldapUser['user'],
                            'role' => $ldapUser['role'],
                            'department' => $ldapUser['department'],
                            'jobTitle'  => $ldapUser['jobTitle'],
                            'jobLevel'  => $ldapUser['jobLevel'],
                            'password' => '',
                            'clientId' => '',
                            'source' => 'ldap',
                            'status' => 'a',
                        );

                        $userId = $this->userRepo->addUser($userArray);

                        if ($userId !== false) {
                            $user = $this->userRepo->getUserByEmail($usernameWDomain);
                        } else {
                            error_log("Ldap user creation failed.");
                            return false;
                        }

                        //TODO: create a better login response. This will return that the username or password was not correct
                    } else {
                        $user['firstname'] = $ldapUser['firstname'];
                        $user['lastname'] = $ldapUser['lastname'];
                        $user['phone'] = $ldapUser['phone'];
                        $user['user'] = $user['username'];
                        $user['department'] = $ldapUser['department'];
                        $user['jobTitle'] = $ldapUser['jobTitle'];
                        $user['jobLevel']  = $ldapUser['jobLevel'];

                        $this->userRepo->editUser($user, $user['id']);
                    }

                    if ($user !== false && is_array($user)) {
                        $this->setUserSession($user, true);

                        return true;
                    } else {
                        error_log("Could not retrieve user by email");
                        return false;
                    }
                }

                //Don't return false, to allow the standard login provider to check the db for contractors or clients not in ldap
            } elseif ($this->config->useLdap === true && !extension_loaded('ldap')) {
                error_log("Can't use ldap. Extension not installed");
            }

            //TODO: Single Sign On?
            //Standard login
            //Check if the user is in our db
            //Check even if ldap is turned on to allow contractors and clients to have an account
            $user = $this->authRepo->getUserByLogin($username, $password);

            if ($user !== false && is_array($user)) {
                $this->setUserSession($user);

                self::dispatch_event("afterLoginCheck", ['username' => $username, 'password' => $password, 'authService' => app()->make(self::class)]);
                return true;
            } else {
                self::dispatch_event("afterLoginCheck", ['username' => $username, 'password' => $password, 'authService' => app()->make(self::class)]);
                return false;
            }
        }

        public function setUserSession($user, $isLdap = false)
        {
            if (!$user || !is_array($user)) {
                return false;
            }

            $this->name = htmlentities($user['firstname']);
            $this->mail = filter_var($user['username'], FILTER_SANITIZE_EMAIL);
            $this->userId = $user['id'];
            $this->settings = $user['settings'] ? unserialize($user['settings']) : array();
            $this->clientId = $user['clientId'];
            $this->twoFAEnabled = $user['twoFAEnabled'];
            $this->twoFASecret = $user['twoFASecret'];
            $this->role = Roles::getRoleString($user['role']);
            $this->profileId = $user['profileId'];

            //Set Sessions
            $_SESSION['userdata'] = self::dispatch_filter('user_session_vars', [
                        'role' => $this->role,
                        'id' => $this->userId,
                        'name' => $this->name,
                        'profileId' => $this->profileId,
                        'mail' => $this->mail,
                        'clientId' => $this->clientId,
                        'settings' => $this->settings,
                        'twoFAEnabled' => $this->twoFAEnabled,
                        'twoFAVerified' => false,
                        'twoFASecret' => $this->twoFASecret,
                        'isLdap' => $isLdap,
                        'createdOn' => $user['createdOn'] ?? '',
            ]);

            $this->updateUserSessionDB($this->userId, $this->session);
        }

        public function updateUserSessionDB($userId, $sessionID)
        {
            return $this->authRepo->updateUserSession($userId, $sessionID, time());
        }

        /**
         * logged_in - Check if logged in and Update sessions
         *
         * @access public
         * @return boolean
         */
        public function logged_in()
        {

            //Check if we actually have a php session available
            if (isset($_SESSION['userdata']) === true) {
                return true;

                //If the session doesn't have any session data we are out of sync. Start again
            } else {
                return false;
            }
        }

        public function getSessionId()
        {
            return $this->session;
        }

        /**
         * logout - destroy sessions and cookies
         *
         * @access private
         */
        public function logout()
        {

            $this->authRepo->invalidateSession($this->session);

            SessionCore::destroySession();

            if (isset($_SESSION)) {
                $sessionsToDestroy = self::dispatch_filter('sessions_vars_to_destroy', [
                            'userdata',
                            'template',
                            'subdomainData',
                            'currentProject',
                            'currentSprint',
                            'projectsettings',
                            'currentSubscriptions',
                            'lastTicketView',
                            'lastFilterdTicketTableView',
                ]);

                foreach ($sessionsToDestroy as $key) {
                    unset($_SESSION[$key]);
                }

                self::dispatch_event("afterSessionDestroy", ['authService' => app()->make(self::class)]);
            }
        }

        /**
         * validateResetLink - validates that the password reset link belongs to a user account in the database
         *
         * @access public
         * @param string $hash invite link hash
         * @return boolean
         */
        public function validateResetLink(string $hash)
        {

            return $this->authRepo->validateResetLink($hash);
        }

        /**
         * getUserByInviteLink - gets the user by invite link
         *
         * @access public
         * @param string $hash invite link hash
         * @return array|boolean
         */
        public function getUserByInviteLink($hash)
        {
            return $this->authRepo->getUserByInviteLink($hash);
        }

        /**
         * generateLinkAndSendEmail - generates an invite link (hash) and sends email to user
         *
         * @access public
         * @param string $username new user to be invited (email)
         * @return boolean returns true on success, false on failure
         */
        public function generateLinkAndSendEmail(string $username): bool
        {

            $userFromDB = $this->userRepo->getUserByEmail($_POST["username"]);

            if ($userFromDB !== false && count($userFromDB) > 0) {
                if ($userFromDB['pwResetCount'] < $this->pwResetLimit) {
                    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
                    $resetLink = substr(str_shuffle($permitted_chars), 0, 32);

                    $result = $this->authRepo->setPWResetLink($username, $resetLink);

                    if ($result) {
                        //Don't queue, send right away
                        $mailer = app()->make(MailerCore::class);
                        $mailer->setContext('password_reset');
                        $mailer->setSubject($this->language->__('email_notifications.password_reset_subject'));
                        $actual_link = "" . BASE_URL . "/auth/resetPw/" . $resetLink;
                        $mailer->setHtml(sprintf($this->language->__('email_notifications.password_reset_message'), $actual_link));
                        $to = array($username);
                        $mailer->sendMail($to, "Leantime System");

                        return true;
                    }
                } elseif ($this->config->debug) {
                    error_log(
                        "PW reset failed: maximum request count has been reached for user " . $userFromDB['id']
                    );
                }
            }

            return false;
        }

        public function changePw($password, $hash): bool
        {
            return $this->authRepo->changePW($password, $hash);
        }

        public static function userIsAtLeast(string $role, $forceGlobalRoleCheck = false)
        {

            //Force Global Role check to circumvent projectRole checks for global controllers (users, projects, clients etc)
            $roleToCheck = self::getRoleToCheck($forceGlobalRoleCheck);

            if ($roleToCheck === false) {
                return false;
            }

            $testKey = array_search($role, Roles::getRoles());

            if ($role == "" || $testKey === false) {
                error_log("Check for invalid role detected: " . $role);
                return false;
            }

            $currentUserKey = array_search($roleToCheck, Roles::getRoles());

            if ($testKey <= $currentUserKey) {
                return true;
            } else {
                return false;
            }
        }

        public static function authOrRedirect($role, $forceGlobalRoleCheck = false): mixed
        {

            if (self::userHasRole($role, $forceGlobalRoleCheck)) {
                return true;
            } else {
                FrontcontrollerCore::redirect(BASE_URL . "/errors/error403");
            }

            return false;
        }

        public static function userHasRole(string|array $role, $forceGlobalRoleCheck = false): bool
        {

            //Force Global Role check to circumvent projectRole checks for global controllers (users, projects, clients etc)
            $roleToCheck = self::getRoleToCheck($forceGlobalRoleCheck);

            if (is_array($role) && in_array($roleToCheck, $role)) {
                return true;
            } elseif ($role == $roleToCheck) {
                return true;
            }

            return false;
        }

        public static function getRole()
        {
        }

        public static function getUserClientId()
        {
            return $_SESSION['userdata']['clientId'];
        }

        public static function getUserId()
        {
            return $_SESSION['userdata']['id'];
        }

        public function use2FA()
        {
            return $_SESSION['userdata']['twoFAEnabled'];
        }

        public function verify2FA($code)
        {
            $tfa = new TwoFactorAuth('Leantime');
            return $tfa->verifyCode($_SESSION['userdata']['twoFASecret'], $code);
        }

        public function get2FAVerified()
        {
            return $_SESSION['userdata']['twoFAVerified'];
        }

        public function set2FAVerified()
        {
            $_SESSION['userdata']['twoFAVerified'] = true;
        }
    }

}
