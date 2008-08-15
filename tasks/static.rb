require './tasks/helpers/minify.rb'

require 'fileutils'

namespace :static do
  STATIC_DIR = File.expand_path(File.join(File.dirname(__FILE__),'..','static'))

  MINIFY = [
    {
      :path => File.join('rpc','server.php'),
      :output => File.join('rpc','server.min.php')
    }
  ]

  desc 'Creates minified versions of all static files'
  task :minify do
    MINIFY.each do |pair|
      path = File.join(STATIC_DIR,pair[:path])
      output = File.join(STATIC_DIR,pair[:output])

      Ronin::PHP.minify(path,output)
    end
  end

  desc 'Cleans all minified static files'
  task :clean do
    MINIFY.each do |pair|
      FileUtils.rm(File.join(STATIC_DIR,pair[:output]))
    end
  end
end
