<?php

namespace GoalioForgotPassword\Service;

use ZfcUser\Options\PasswordOptionsInterface;
use GoalioForgotPassword\Options\ForgotOptionsInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use ZfcUser\Mapper\UserInterface as UserMapperInterface;
use GoalioForgotPassword\Mapper\Password as PasswordMapper;
use Zend\Crypt\Password\Bcrypt;
use Zend\Form\Form;
use ZfcBase\EventManager\EventProvider;

class Password extends EventProvider implements ServiceManagerAwareInterface
{
    protected $options;
    protected $userMapper;
    protected $passwordMapper;
    protected $serviceLocator;
    protected $zfcUserOptions;
    protected $serviceManager;

    /**
     * @param $token
     * @return mixed
     */
    public function findByRequestKey($token)
    {
        return $this->getPasswordMapper()->findByRequestKey($token);
    }

    /**
     * @param $email
     * @return mixed
     */
    public function findByEmail($email)
    {
        return $this->getPasswordMapper()->findByEmail($email);
    }

    /**
     * @return mixed
     */
    public function cleanExpiredForgotRequests()
    {
        // TODO: reset expiry time from options
        return $this->getPasswordMapper()->cleanExpiredForgotRequests();
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function cleanPriorForgotRequests($userId)
    {
        return $this->getPasswordMapper()->cleanPriorForgotRequests($userId);
    }

    /**
     * @param $m
     * @return mixed
     */
    public function remove($m)
    {
        return $this->getPasswordMapper()->remove($m);
    }

    /**
     * @param $userId
     * @param $email
     */
    public function sendProcessForgotRequest($userId, $email)
    {
        // Invalidate all prior request for a new password
        $this->cleanPriorForgotRequests($userId);

        $class = $this->getOptions()->getPasswordEntityClass();
        $model = new $class;
        $model->setUserId($userId);
        $model->setRequestTime(new \DateTime('now'));
        $model->generateRequestKey();
        $this->getEventManager()->trigger(__FUNCTION__, $this, array('record' => $model, 'userId' => $userId));
        $this->getPasswordMapper()->persist($model);

        $config = $this->getServiceManager()->get('config');
        $urlPlugin = $this->getServiceManager()->get('ControllerPluginManager')->get('url');
        $url = $urlPlugin->fromRoute('zfcuser/resetpassword', array('userId' => $userId, 'token' => $model->getRequestKey()));

        $this->getServiceManager()->get('CioBase\Service\Resque')->addJob('PasswordRestore_Job', array
        (
            'email' => $email,
            'link'  => '//' . $config['coursio']['app_domain'] . $url,
        ));
    }

    /**
     * @param $password
     * @param $user
     * @param array $data
     * @return bool
     */
    public function resetPassword($password, $user, array $data)
    {
        $newPass = $data['newCredential'];

        $bcrypt = new Bcrypt;
        $bcrypt->setCost($this->getZfcUserOptions()->getPasswordCost());

        $pass = $bcrypt->create($newPass);
        $user->setPassword($pass);

        $this->getEventManager()->trigger(__FUNCTION__, $this, array('user' => $user));
        $this->getUserMapper()->update($user);
        $this->remove($password);
        $this->getEventManager()->trigger(__FUNCTION__.'.post', $this, array('user' => $user));

        return true;
    }

    /**
     * @return mixed
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * @param ServiceManager $serviceManager
     * @return $this
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
        return $this;
    }

    /**
     * getUserMapper
     *
     * @return UserMapperInterface
     */
    public function getUserMapper()
    {
        if (null === $this->userMapper)
        {
            $this->userMapper = $this->getServiceManager()->get('zfcuser_user_mapper');
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
     * @param PasswordMapper $passwordMapper
     * @return $this
     */
    public function setPasswordMapper(PasswordMapper $passwordMapper)
    {
        $this->passwordMapper = $passwordMapper;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPasswordMapper()
    {
        if (null === $this->passwordMapper)
        {
            $this->setPasswordMapper($this->getServiceManager()->get('goalioforgotpassword_password_mapper'));
        }

        return $this->passwordMapper;
    }

    /**
     * @return ForgotOptionsInterface
     */
    public function getOptions()
    {
        if (!$this->options instanceof ForgotOptionsInterface)
        {
            $this->setOptions($this->getServiceManager()->get('goalioforgotpassword_module_options'));
        }
        return $this->options;
    }

    /**
     * @param ForgotOptionsInterface $opt
     * @return $this
     */
    public function setOptions(ForgotOptionsInterface $opt)
    {
        $this->options = $opt;
        return $this;
    }

    /**
     * @return PasswordOptionsInterface
     */
    public function getZfcUserOptions()
    {
        if (!$this->zfcUserOptions instanceof PasswordOptionsInterface)
        {
            $this->setZfcUserOptions($this->getServiceManager()->get('zfcuser_module_options'));
        }
        return $this->zfcUserOptions;
    }

    /**
     * @param PasswordOptionsInterface $zfcUserOptions
     * @return $this
     */
    public function setZfcUserOptions(PasswordOptionsInterface $zfcUserOptions)
    {
        $this->zfcUserOptions = $zfcUserOptions;
        return $this;
    }
}
