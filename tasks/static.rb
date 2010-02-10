lib_dir = File.expand_path(File.join(File.dirname(__FILE__),'..','lib'))
unless $LOAD_PATH.include?(lib_dir)
  $LOAD_PATH << lib_dir
end

require 'ronin/gen'

namespace :php do
  namespace :rpc do
    GEN_DIR = File.join('static','ronin','gen','php','rpc')
    STATIC_DIR = File.join('static','ronin','php','rpc')
    Generator = Ronin::Gen.generator('php:rpc_server')

    directory STATIC_DIR

    file File.join(STATIC_DIR,'server.php') => (
      Dir[File.join(GEN_DIR,'*.php')] +
      [File.join(GEN_DIR,'server.php.erb')]
    ) do |t|
      Generator.generate(
        {:no_ajax => true},
        [t.name]
      )
    end

    file File.join(STATIC_DIR,'server.ajax.php') => (
      Dir[File.join(GEN_DIR,'**','*')]
    ) do |t|
      Generator.generate({},[t.name])
    end
  end
end
