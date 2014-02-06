<?php

namespace GoalioForgotPassword\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use GoalioForgotPassword\Service\Password as PasswordService;
use GoalioForgotPassword\Options\ForgotControllerOptionsInterface;

class ForgotController extends AbstractActionController
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var PasswordService
     */
    protected $passwordService;

    /**
     * @var Form
     */
    protected $forgotForm;

    /**
     * @var Form
     */
    protected $resetForm;

    /**
     * @todo Make this dynamic / translation-friendly
     * @var string
     */
    protected $message = 'An e-mail with further instructions has been sent to you.';

    /**
     * @todo Make this dynamic / translation-friendly
     * @var string
     */
    protected $failedMessage = 'The e-mail address is not valid.';

    /**
     * @var ForgotControllerOptionsInterface
     */
    protected $options;

    /**
     * User page
     */
    public function indexAction()
    {
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('zfcuser');
        } else {
            return $this->redirect()->toRoute('zfcuser/forgotpassword');
        }
    }

    public function forgotAction()
    {
        $form    = $this->getForgotForm();
        $service = $this->getPasswordService();
        $service->cleanExpiredForgotRequests();

        if ($this->getRequest()->isPost())
        {
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid())
            {
                $userService = $this->getUserService();

                $email = $this->getRequest()->getPost()->get('email');
                $user = $userService->getUserMapper()->findByEmail($email);

                //only send request when email is found
                if ($user != null)
                {
                    $service->sendProcessForgotRequest($user->getId(), $email);
                }

                $vm = new ViewModel(array
                (
                    'email'   => $email,
                    'message' => $user ? null : 'Email not found',
                ));
                $vm->setTemplate('goalio-forgot-password/forgot/sent');
                return $vm;
            }
            else
            {
                $this->flashMessenger()->setNamespace('goalioforgotpassword-forgot-form')->addMessage($this->failedMessage);
                return array
                (
                    'forgotForm' => $form,
                );
            }
        }

        // Render the form
        return array
        (
            'forgotForm' => $form,
        );
    }

    public function resetAction()
    {
        $form    = $this->getResetForm();
        $service = $this->getPasswordService();
        $service->cleanExpiredForgotRequests();

        $userId   = $this->params()->fromRoute('userId', null);
        $token    = $this->params()->fromRoute('token', null);
        $password = $service->getPasswordMapper()->findByUserIdRequestKey($userId, $token);

        //no request for a new password found
        if($password === null || $password == false)
        {
            return $this->redirect()->toRoute('zfcuser/forgotpassword');
        }

        $userService = $this->getUserService();
        $user = $userService->getUserMapper()->findById($userId);

        if ($this->getRequest()->isPost())
        {
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid() && $user !== null)
            {
                $service->resetPassword($password, $user, $form->getData());

                $vm = new ViewModel(array
                (
                    'email' => $user->getEmail(),
                ));
                $vm->setTemplate('goalio-forgot-password/forgot/passwordchanged');
                return $vm;
            }
        }

        // Render the form
        return array
        (
            'resetForm' => $form,
            'userId'    => $userId,
            'token'     => $token,
            'email'     => $user->getEmail(),
        );
    }

    /**
     * @return array|UserService|object
     */
    public function getUserService()
    {
        if (!$this->userService)
        {
            $this->userService = $this->getServiceLocator()->get('zfcuser_user_service');
        }
        return $this->userService;
    }

    /**
     * @param UserService $userService
     * @return $this
     */
    public function setUserService(UserService $userService)
    {
        $this->userService = $userService;
        return $this;
    }

    /**
     * @return array|PasswordService|object
     */
    public function getPasswordService()
    {
        if (!$this->passwordService)
        {
            $this->passwordService = $this->getServiceLocator()->get('goalioforgotpassword_password_service');
        }
        return $this->passwordService;
    }

    /**
     * @param PasswordService $passwordService
     * @return $this
     */
    public function setPasswordService(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
        return $this;
    }

    /**
     * @return Form
     */
    public function getForgotForm()
    {
        if (!$this->forgotForm)
        {
            $this->setForgotForm($this->getServiceLocator()->get('goalioforgotpassword_forgot_form'));
        }
        return $this->forgotForm;
    }

    /**
     * @param Form $forgotForm
     */
    public function setForgotForm(Form $forgotForm)
    {
        $this->forgotForm = $forgotForm;
    }

    /**
     * @return Form
     */
    public function getResetForm()
    {
        if (!$this->resetForm)
        {
            $this->setResetForm($this->getServiceLocator()->get('goalioforgotpassword_reset_form'));
        }
        return $this->resetForm;
    }

    /**
     * @param Form $resetForm
     */
    public function setResetForm(Form $resetForm)
    {
        $this->resetForm = $resetForm;
    }

    /**
     * set options
     *
     * @param ForgotControllerOptionsInterface $options
     * @return ForgotController
     */
    public function setOptions(ForgotControllerOptionsInterface $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * get options
     *
     * @return ForgotControllerOptionsInterface
     */
    public function getOptions()
    {
        if (!$this->options instanceof ForgotControllerOptionsInterface)
        {
            $this->setOptions($this->getServiceLocator()->get('goalioforgotpassword_module_options'));
        }
        return $this->options;
    }
}
