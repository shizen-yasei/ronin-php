# -*- ruby -*-

require 'rubygems'
require 'hoe'
require 'hoe/signing'
require './tasks/spec.rb'
require './tasks/static.rb'
require './lib/ronin/php/version.rb'

Hoe.spec('ronin-php') do
  self.rubyforge_name = 'ronin'
  self.developer('Postmodern','postmodern.mod3@gmail.com')
  self.remote_rdoc_dir = 'docs/ronin-php'
  self.extra_deps = [
    'cssmin',
    'jsmin',
    'hpricot',
    ['ronin', '>=0.2.2'],
    ['ronin-web', '>=0.1.2']
  ]
end

# vim: syntax=Ruby
