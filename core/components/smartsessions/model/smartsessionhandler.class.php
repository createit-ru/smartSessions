<?php

require_once MODX_CORE_PATH . 'model/modx/modsessionhandler.class.php';

class smartSessionHandler extends modSessionHandler
{

    private $initialized = false;

    /**
     * @var int Максимальное время жизни для сессий различных ботов, описанных в $botSignatures
     */
    public $botsMaxLifetime = 0;

    /**
     * @var int Максимальное время жизни для сессий с пустым User-Agent (поле user_agent)
     */
    public $emptyUserAgentMaxLifetime = 0;

    /**
     * @var int Максимальное время жизни для сессий авторизованных пользователей
     */
    public $authorizedUsersMaxLifetime = 0;

    /**
     * @var array Список сигнатур различных ботов
     */
    private $botSignatures = array();

    /**
     * @var smartSession Переопределяем, т.к. в базовом классе этот член - private
     */
    protected $session = null;


    /**
     * @param modX $modx
     */
    function __construct(modX &$modx)
    {
        parent::__construct($modx);
        $this->modx->addPackage('smartsessions', MODX_CORE_PATH . 'components/smartsessions/model/');
    }

    private function initialize()
    {
        $botsMaxlifetime = (integer)$this->modx->getOption('smartsessions_bots_gc_maxlifetime');
        if ($botsMaxlifetime > 0) {
            $this->botsMaxLifetime = $botsMaxlifetime;
        } else {
            $this->botsMaxLifetime = $this->gcMaxLifetime;
        }

        $botSignatures = $this->modx->getOption('smartsessions_bot_signatures');
        $botSignatures = explode('|', $botSignatures);
        $botSignatures = array_map('trim', $botSignatures);

        $this->botSignatures = $botSignatures;

        $authorizedUsersMaxLifetime = (integer)$this->modx->getOption('smartsessions_authorized_users_gc_maxlifetime');
        if ($authorizedUsersMaxLifetime > 0) {
            $this->authorizedUsersMaxLifetime = $authorizedUsersMaxLifetime;
        } else {
            $this->authorizedUsersMaxLifetime = $this->gcMaxLifetime;
        }

        $emptyUserAgentMaxLifetime = (integer)$this->modx->getOption('smartsessions_empty_user_agent_gc_maxlifetime');
        if ($emptyUserAgentMaxLifetime > 0) {
            $this->emptyUserAgentMaxLifetime = $emptyUserAgentMaxLifetime;
        } else {
            $this->emptyUserAgentMaxLifetime = $this->gcMaxLifetime;
        }

        $this->initialized = true;
    }


    /**
     * Reads a specific {@link smartSession} record's data.
     *
     * @access public
     * @param integer $id The pk of the {@link smartSession} object.
     * @return string The data read from the {@link smartSession} object.
     */
    public function read($id)
    {
        if ($this->_getSession($id)) {
            $data = $this->session->get('data');
        } else {
            $data = '';
        }
        return (string)$data;
    }

    /**
     * Writes data to a specific {@link smartSession} object.
     *
     * @access public
     * @param integer $id The PK of the smartSession object.
     * @param mixed $data The data to write to the session.
     * @return boolean True if successfully written.
     */
    public function write($id, $data)
    {
        $written = false;
        if ($this->_getSession($id, true)) {
            $this->session->set('data', $data);
            if ($this->session->isNew()
                || $this->session->isDirty('data')
                || ($this->cacheLifetime > 0 && (time() - strtotime($this->session->get('access'))) > $this->cacheLifetime)) {
                $this->session->set('access', time());

                $userAgent = $this->getUserAgent();
                $this->session->set('user_id', $this->getUserId());
                $this->session->set('user_agent', $userAgent);
                $this->session->set('is_bot', $this->isBot($userAgent));
                $this->session->set('ip', $this->getIp());
            }
            $written = $this->session->save($this->cacheLifetime);
        }
        return $written;
    }

    /**
     * Destroy a specific {@link smartSession} record.
     *
     * @access public
     * @param integer $id
     * @return boolean True if the session record was destroyed.
     */
    public function destroy($id)
    {
        if ($this->_getSession($id)) {
            $destroyed = $this->session->remove();
        } else {
            $destroyed = true;
        }
        return $destroyed;
    }

    /**
     * Remove any expired sessions.
     *
     * @access public
     * @param integer $max The amount of time since now to expire any session
     * longer than.
     * @return boolean True if session records were removed.
     */
    public function gc($max)
    {
        if (!$this->initialized) {
            $this->initialize();
        }
        $result1 = $this->removeBotSessions();
        $result2 = $this->removeEmptyUserAgentSessions();
        $result3 = $this->removeAuthorizedUserSessions();
        $result4 = $this->removeCommonSessions();

        return $result1 !== false
            && $result2 !== false
            && $result3 !== false
            && $result4 !== false;
    }


    /**
     * Удаление сессий ботов
     * @return int|false
     */
    private function removeBotSessions()
    {
        $maxLifeTime = time() - $this->botsMaxLifetime;

        $criteria = array(
            'access:<' => $maxLifeTime,
            'is_bot:=' => 1,
        );
        return $this->modx->removeCollection('smartSession', $criteria);
    }

    /**
     * Удаление сессий с пустым User-Agent
     * @return int|false
     */
    private function removeEmptyUserAgentSessions()
    {
        $maxLifeTime = time() - $this->emptyUserAgentMaxLifetime;

        $criteria = array(
            'access:<' => $maxLifeTime,
            'user_agent:=' => '',
        );
        return $this->modx->removeCollection('smartSession', $criteria);
    }

    /**
     * Удаление сессий авторизованных пользователей
     * @return bool
     */
    private function removeAuthorizedUserSessions()
    {
        $maxLifeTime = time() - $this->authorizedUsersMaxLifetime;

        $criteria = array(
            'access:<' => $maxLifeTime,
            'user_id:>' => 0,
        );
        return $this->modx->removeCollection('smartSession', $criteria);
    }

    /**
     * Удаление обычных сессий
     * @return int|false
     */
    private function removeCommonSessions()
    {
        $maxLifeTime = time() - $this->gcMaxLifetime;
        $criteria = array(
            'access:<' => $maxLifeTime
        );
        // Если время хранения сессий авторизованных пользователей больше, чем общее время,
        // то не будем удаляем эти записи
        if ($this->authorizedUsersMaxLifetime > $this->gcMaxLifetime) {
            $criteria[] = array(
                'user_id:IS' => null,
                'OR:user_id:=' => 0
            );
        }

        return $this->modx->removeCollection('smartSession', $criteria);
    }


    /**
     * Gets the {@link smartSession} object, respecting the cache flag represented by cacheLifetime.
     *
     * @access protected
     * @param integer $id The PK of the {@link smartSession} record.
     * @param boolean $autoCreate If true, will automatically create the session
     * record if none is found.
     * @return smartSession|null The smartSession instance loaded from db or auto-created; null if it
     * could not be retrieved and/or created.
     */
    protected function _getSession($id, $autoCreate = false)
    {
        $this->session = $this->modx->getObject('smartSession', array('id' => $id), $this->cacheLifetime);
        if ($autoCreate && !is_object($this->session)) {
            $this->modx->getRequest();

            $userAgent = $this->getUserAgent();

            $this->session = $this->modx->newObject('smartSession');
            $this->session->set('id', $id);
            $this->session->set('user_id', $this->getUserId());
            $this->session->set('user_agent', $userAgent);
            $this->session->set('is_bot', $this->isBot($userAgent));
            $this->session->set('ip', $this->getIp());
        }
        if (!($this->session instanceof smartSession)
            || $id != $this->session->get('id')
            || !$this->session->validate()) {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'There was an error retrieving or creating session id: ' . $id);
        }
        return $this->session;
    }

    private function getIp(){
        if($this->modx instanceof modX) {
            $ip = $this->modx->request->getClientIp();
            // Ограничим длину сохраняемого ip, т.к. иногда приходят не корректные данные
            return substr($ip['ip'], 0, 45);
        }
        return '';
    }

    private function getUserAgent()
    {
        $user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        if (empty($user_agent)) {
            return '';
        }
        return substr($user_agent, 0, 255);
    }

    /**
     * @return int|null
     */
    private function getUserId()
    {
        if ($this->modx instanceof modX) {
            $contextKey = $this->modx->context ? $this->modx->context->key : '';
            if ($contextKey && isset ($_SESSION['modx.user.contextTokens'][$contextKey])) {
                return intval($_SESSION['modx.user.contextTokens'][$contextKey]);
            }
        }
        return null;
    }

    private function isBot($userAgent)
    {
        if(!$this->initialized) {
            $this->initialize();
        }
        if (!empty($userAgent)) {
            foreach ($this->botSignatures as $botSignature) {
                if (strpos($userAgent, $botSignature) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
}