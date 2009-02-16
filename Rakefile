# -*- ruby -*-

require 'rubygems'
require 'hoe'
require './tasks/spec.rb'
require './tasks/static.rb'
require './lib/ronin/php/version.rb'

Hoe.new('ronin-php', Ronin::PHP::VERSION) do |p|
  p.rubyforge_name = 'ronin'
  p.developer('Postmodern','postmodern.mod3@gmail.com')
  p.remote_rdoc_dir = 'docs/ronin-php'
  p.extra_deps = [
    'cssmin',
    'jsmin',
    ['ronin', '>=0.2.1'],
    ['ronin-web', '>=0.1.0']
  ]
end

# vim: syntax=Ruby
