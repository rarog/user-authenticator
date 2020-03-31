<?php

namespace UserAuthenticator\Service;

use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Form;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Crypt\Password\Bcrypt;
use Laminas\Hydrator;
use UserAuthenticator\EventManager\EventProvider;
use UserAuthenticator\Form\ChangePassword;
use UserAuthenticator\Form\Register;
use UserAuthenticator\Mapper\UserInterface as UserMapperInterface;
use UserAuthenticator\Mapper\User as UserMapper;
use UserAuthenticator\Options\ModuleOptions;
use UserAuthenticator\Options\UserServiceOptionsInterface;
use Laminas\Hydrator\ClassMethodsHydrator;

class User extends EventProvider
{

    /**
     * @var UserMapperInterface
     */
    protected $userMapper;

    /**
     * @var AuthenticationService
     */
    protected $authService;

    /**
     * @var Form
     */
    protected $loginForm;

    /**
     * @var Form
     */
    protected $registerForm;

    /**
     * @var Form
     */
    protected $changePasswordForm;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var UserServiceOptionsInterface
     */
    protected $options;

    /**
     * @var Hydrator\ClassMethods
     */
    protected $formHydrator;

    /**
     * createFromForm
     *
     * @param array $data
     * @return \UserAuthenticator\Model\UserInterface
     * @throws Exception\InvalidArgumentException
     */
    public function register(array $data)
    {
        $class = $this->getOptions()->getUserEntityClass();
        $user = new $class();
        $form = $this->getRegisterForm();
        $form->setHydrator($this->getFormHydrator());
        $form->bind($user);
        $form->setData($data);
        if (! $form->isValid()) {
            return false;
        }

        $user = $form->getData();
        /* @var $user \UserAuthenticator\Model\UserInterface */

        $bcrypt = new Bcrypt();
        $bcrypt->setCost($this->getOptions()->getPasswordCost());
        $user->setPassword($bcrypt->create($user->getPassword()));

        if ($this->getOptions()->getEnableUsername()) {
            $user->setUsername($data['username']);
        }
        if ($this->getOptions()->getEnableDisplayName()) {
            $user->setDisplayName($data['display_name']);
        }

        // If user state is enabled, set the default state value
        if ($this->getOptions()->getEnableUserState()) {
            $user->setState($this->getOptions()->getDefaultUserState());
        }
        $this->getEventManager()->trigger(__FUNCTION__, $this, ['user' => $user, 'form' => $form]);
        $this->getUserMapper()->insert($user);
        $this->getEventManager()->trigger(__FUNCTION__ . '.post', $this, ['user' => $user, 'form' => $form]);
        return $user;
    }

    /**
     * change the current users password
     *
     * @param array $data
     * @return boolean
     */
    public function changePassword(array $data)
    {
        $currentUser = $this->getAuthService()->getIdentity();

        $oldPass = $data['credential'];
        $newPass = $data['newCredential'];

        $bcrypt = new Bcrypt();
        $bcrypt->setCost($this->getOptions()->getPasswordCost());

        if (! $bcrypt->verify($oldPass, $currentUser->getPassword())) {
            return false;
        }

        $pass = $bcrypt->create($newPass);
        $currentUser->setPassword($pass);

        $this->getEventManager()->trigger(__FUNCTION__, $this, ['user' => $currentUser, 'data' => $data]);
        $this->getUserMapper()->update($currentUser);
        $this->getEventManager()->trigger(__FUNCTION__ . '.post', $this, ['user' => $currentUser, 'data' => $data]);

        return true;
    }

    public function changeEmail(array $data)
    {
        $currentUser = $this->getAuthService()->getIdentity();

        $bcrypt = new Bcrypt();
        $bcrypt->setCost($this->getOptions()->getPasswordCost());

        if (! $bcrypt->verify($data['credential'], $currentUser->getPassword())) {
            return false;
        }

        $currentUser->setEmail($data['newIdentity']);

        $this->getEventManager()->trigger(__FUNCTION__, $this, ['user' => $currentUser, 'data' => $data]);
        $this->getUserMapper()->update($currentUser);
        $this->getEventManager()->trigger(__FUNCTION__ . '.post', $this, ['user' => $currentUser, 'data' => $data]);

        return true;
    }

    /**
     * getUserMapper
     *
     * @return UserMapperInterface
     */
    public function getUserMapper()
    {
        if (null === $this->userMapper) {
            $this->userMapper = $this->getServiceManager()->get(UserMapper::class);
        }
        return $this->userMapper;
    }

    /**
     * setUserMapper
     *
     * @param UserMapperInterface $userMapper
     * @return User
     */
    public function setUserMapper(UserMapperInterface $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * getAuthService
     *
     * @return AuthenticationService
     */
    public function getAuthService()
    {
        if (null === $this->authService) {
            $this->authService = $this->getServiceManager()->get('zfcuser_auth_service');
        }
        return $this->authService;
    }

    /**
     * setAuthenticationService
     *
     * @param AuthenticationService $authService
     * @return User
     */
    public function setAuthService(AuthenticationService $authService)
    {
        $this->authService = $authService;
        return $this;
    }

    /**
     * @return Form
     */
    public function getRegisterForm()
    {
        if (null === $this->registerForm) {
            $this->registerForm = $this->getServiceManager()->get(Register::class);
        }
        return $this->registerForm;
    }

    /**
     * @param Form $registerForm
     * @return User
     */
    public function setRegisterForm(Form $registerForm)
    {
        $this->registerForm = $registerForm;
        return $this;
    }

    /**
     * @return Form
     */
    public function getChangePasswordForm()
    {
        if (null === $this->changePasswordForm) {
            $this->changePasswordForm = $this->getServiceManager()->get(ChangePassword::class);
        }
        return $this->changePasswordForm;
    }

    /**
     * @param Form $changePasswordForm
     * @return User
     */
    public function setChangePasswordForm(Form $changePasswordForm)
    {
        $this->changePasswordForm = $changePasswordForm;
        return $this;
    }

    /**
     * get service options
     *
     * @return UserServiceOptionsInterface
     */
    public function getOptions()
    {
        if (! $this->options instanceof UserServiceOptionsInterface) {
            $this->setOptions($this->getServiceManager()->get(ModuleOptions::class));
        }
        return $this->options;
    }

    /**
     * set service options
     *
     * @param UserServiceOptionsInterface $options
     */
    public function setOptions(UserServiceOptionsInterface $options)
    {
        $this->options = $options;
    }

    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Set service manager instance
     *
     * @param ContainerInterface $serviceManager
     * @return User
     */
    public function setServiceManager(ContainerInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
        return $this;
    }

    /**
     * Return the Form Hydrator
     *
     * @return \Laminas\Hydrator\ClassMethodsHydrator
     */
    public function getFormHydrator()
    {
        if (! $this->formHydrator instanceof Hydrator\HydratorInterface) {
            $this->setFormHydrator($this->getServiceManager()->get(ClassMethodsHydrator::class));
        }

        return $this->formHydrator;
    }

    /**
     * Set the Form Hydrator to use
     *
     * @param Hydrator\HydratorInterface $formHydrator
     * @return User
     */
    public function setFormHydrator(Hydrator\HydratorInterface $formHydrator)
    {
        $this->formHydrator = $formHydrator;
        return $this;
    }
}