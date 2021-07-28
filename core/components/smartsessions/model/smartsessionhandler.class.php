<?php

require_once  MODX_CORE_PATH.'model/modx/modsessionhandler.class.php';

class smartSessionHandler extends modSessionHandler {

    /**
     * @var int Максимальное время жизни для сессий различных ботов, описанных в $botSignatures
     */
    public $botsMaxLifetime = 60 *60 * 1;

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
    function __construct(modX &$modx) {
        parent::__construct($modx);

        $botsMaxlifetime = (integer) $this->modx->getOption('smartsessions_bots_gc_maxlifetime');
        if ($botsMaxlifetime > 0) {
            $this->botsMaxLifetime = $botsMaxlifetime;
        } else {
            $this->botsMaxLifetime = $this->gcMaxLifetime;
        }


        $botSignatures = $this->modx->getOption('smartsessions_bot_signaturess');
        $botSignatures = explode("|", $botSignatures);
        $botSignatures = array_map("trim", $botSignatures);

        $this->botSignatures = $botSignatures;

        $this->modx->addPackage('smartsessions', MODX_CORE_PATH . 'components/smartsessions/model/');
    }



    /**
     * Reads a specific {@link smartSession} record's data.
     *
     * @access public
     * @param integer $id The pk of the {@link smartSession} object.
     * @return string The data read from the {@link smartSession} object.
     */
    public function read($id) {
        if ($this->_getSession($id)) {
            $data= $this->session->get('data');
        } else {
            $data= '';
        }
        return (string) $data;
    }

    /**
     * Writes data to a specific {@link smartSession} object.
     *
     * @access public
     * @param integer $id The PK of the smartSession object.
     * @param mixed $data The data to write to the session.
     * @return boolean True if successfully written.
     */
    public function write($id, $data) {
        $written= false;
        if ($this->_getSession($id, true)) {
            $this->session->set('data', $data);
            if ($this->session->isNew() || $this->session->isDirty('data') || ($this->cacheLifetime > 0 && (time() - strtotime($this->session->get('access'))) > $this->cacheLifetime)) {
                $this->session->set('access', time());
            }
            $written= $this->session->save($this->cacheLifetime);
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
    public function destroy($id) {
        if ($this->_getSession($id)) {
            $destroyed= $this->session->remove();
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
    public function gc($max) {
        $tableName = $this->modx->getTableName('smartSession');

        // Удаление сессий различных ботов
        $botsMaxtime = time() - $this->botsMaxLifetime;
        foreach ($this->botSignatures as $bot) {
            $this->modx->exec("DELETE FROM {$tableName} WHERE `access` < {$botsMaxtime} AND `user_agent` LIKE \"%{$bot}%\";");
        }

        // Обычное удаление устаревших сессий
        $maxtime = time() - $this->gcMaxLifetime;
        // TODO: может быть стоит заменить на запрос DELETE, как выше?
        $result = $this->modx->removeCollection('smartSession', array("{$this->modx->escape('access')} < {$maxtime}"));
        return $result !== false;
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
    protected function _getSession($id, $autoCreate= false) {
        $this->session= $this->modx->getObject('smartSession', array('id' => $id), $this->cacheLifetime);
        if ($autoCreate && !is_object($this->session)) {
            $this->modx->getRequest();
            $ip = $this->modx->request->getClientIp();

            $this->session= $this->modx->newObject('smartSession');
            $this->session->set('id', $id);
            $this->session->set('user_agent', $_SERVER['HTTP_USER_AGENT']);
            $this->session->set('ip', $ip['ip']);

            $user = $this->modx->getAuthenticatedUser($this->modx->context ? $this->modx->context->key : '');
            if($user) {
                $this->session->set('user_id', $user->get('id'));
            }

        }
        if (!($this->session instanceof smartSession) || $id != $this->session->get('id') || !$this->session->validate()) {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'There was an error retrieving or creating session id: ' . $id);
        }
        return $this->session;
    }
}