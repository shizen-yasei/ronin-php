# -*- ruby -*-

require 'rubygems'
require 'hoe'
require 'hoe/signing'
require './tasks/spec.rb'
require './tasks/yard.rb'
require './tasks/static.rb'

Hoe.plugin :yard

Hoe.spec('ronin-php') do
  self.rubyforge_name = 'ronin'
  self.developer('Postmodern','postmodern.mod3@gmail.com')

  self.yard_title = 'Ronin PHP Documentation'
  self.yard_options += ['--protected']
  self.remote_rdoc_dir = 'docs/ronin-php'

  self.extra_deps = [
    ['ronin', '>=0.4.0'],
    ['ronin-gen', '>=0.3.0'],
    ['ronin-web', '>=0.2.2']
  ]

  self.extra_dev_deps = [
    ['rspec', '>=1.2.9'],
    ['yard', '>=0.5.3'],
    ['cssmin', '>=1.0.2'],
    ['jsmin', '>=1.0.1']
  ]
end

# vim: syntax=Ruby
