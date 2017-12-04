<?php 

//need Ev library http://php.net/manual/fr/book.ev.php
class eventloop_libev extends core_websockets {
  public function run($apps) {
    var_dump($apps);
    $this->apps=$apps;
    $this->mem = memory_get_usage();
    switch(Ev::backend()) {
      case Ev::BACKEND_SELECT : $backend="Select()";
        break;
      case Ev::BACKEND_POLL   : $backend="Poll";
        break;
      case Ev::BACKEND_EPOLL  : $backend="Epoll"; // use by linux before and after kernels 2.6.9
        break;
      case Ev::BACKEND_KQUEUE : $backend="Kqueue"; // use by BSD systems
        break;
      case Ev::BACKEND_PORT   : $backend="Event Port"; // use by Solaris systems
        break;     
    }
    $this->stdout("RUNNING with libev method BACKEND : $backend");
    $w_listen = new EvIo($this->master, Ev::READ, function ($w) {
      $client = socket_accept($this->master);
      if ( $client === FALSE) {
        $this->stdout("Failed: socket_accept()");
       // continue;
      }
      else if ( $client > 0) {
        socket_set_nonblock($client)               or $this->stdout("Failed: socket_set_nonblock() id #".$client);
        $user = $this->connect($client);
        $this->stdout("Client #$this->nbclient connected. " . $client);
        $w_read = new EvIo($client, Ev::READ , function ($wr) use ($client,&$user) {
          $start=$this->getrps();
            $this->stdout("read callback",true);
            $this->cb_read($user);
          $this->getrps($start,'<< read');
        });
        $this->readWatchers[$user->id]=&$w_read;
        $w_read->start();
      }		  
    });
    Ev::run();
  }

  protected function addWriteWatchers(&$user) {
    $w_write = new EvIo($user->socket, Ev::WRITE , function () use (&$user) {
      $start=$this->getrps();
        if ($user->writeNeeded) {
          $this->stdout("write callback",true);
          $this->ws_write($user);
        }
      $this->getrps($start,'>> write');
    });
    $this->writeWatchers[$user->id]=&$w_write;
    $w_write->start();	    
  }

  protected function removeWriteWatchers(&$user) {
    $this->writeWatchers[$user->id]->stop();
    $this->writewatchers[$user->id]=null;
    unset($this->writeWatchers[$user->id]);
  }

  protected function ws_server($addr,$port){
    $master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
    socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
    socket_bind($master, $addr, $port)                      or die("Failed: socket_bind()");
    socket_listen($master,1024)                             or die("Failed: socket_listen()");
    socket_set_nonblock($master)							              or die("Failed: socket_set_nonblock()");
    return $master;
  }

  protected function ws_write_t($handle,$buffer) {
     $sent = socket_write($handle, $buffer,$this->maxBufferSize);
     return $sent;
  }

  protected function ws_read_t($socket,&$buffer,$maxBufferSize) {
     $numBytes = socket_recv($socket,$buffer,$maxBufferSize,0);
     return $numBytes;
  }
 
  protected function ws_close_t($handle) {
    socket_shutdown($handle, 2);
  }
}

