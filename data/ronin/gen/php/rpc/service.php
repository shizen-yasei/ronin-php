class Service
{
  var $methods;

  var $persistant;

  function Service()
  {
    $this->methods = array();
    $this->persistant = array();
  }

  function is_windows()
  {
    return substr(PHP_OS, 0, 3) == 'WIN';
  }
}
