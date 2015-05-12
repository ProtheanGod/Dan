<?php namespace Dan\Irc\Location;


use Dan\Database\Savable;

class User extends Location implements Savable {

    protected $nick;
    protected $user;
    protected $host;
    protected $rank;

    public function __construct(array $data)
    {
        parent::__construct();

        $this->nick     = $data['nick'];
        $this->user     = $data['user'];
        $this->host     = $data['host'];
        $this->location = $data['nick'];
        $this->rank     = isset($data['rank']) ? $data['rank'] : null;

        if($this->rank != null)
            $this->setPrefix($this->rank);

        $this->save();
    }

    /**
     * @return string
     */
    public function nick()
    {
        return $this->nick;
    }

    /**
     * @return string
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function host()
    {
        return $this->host;
    }
    /**
     *
     */
    public function save()
    {
        database()->insertOrUpdate('users', ['nick' => $this->nick], [
           'nick' => $this->nick,
           'user' => $this->user,
           'host' => $this->host,
        ]);
    }
}