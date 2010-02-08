# -*- ruby -*-

require 'rubygems'
require 'hoe'
require 'hoe/signing'
require './tasks/static.rb'

Hoe.plugin :yard

Hoe.spec('ronin-php') do
  self.rubyforge_name = 'ronin'
  self.developer('Postmodern','postmodern.mod3@gmail.com')

  self.rspec_options += ['--colour', '--format', 'specdoc']

  self.yard_title = 'Ronin PHP Documentation'
  self.yard_options += ['--markup', 'markdown', '--protected']
  self.remote_yard_dir = 'docs/ronin-php'

  self.extra_deps = [
    ['cssmin', '>=1.0.2'],
    ['jsmin', '>=1.0.1'],
    ['ronin', '>=0.4.0'],
    ['ronin-gen', '>=0.3.0'],
    ['ronin-web', '>=0.2.2'],
    ['ronin-exploits', '>=0.3.2']
  ]

  self.extra_dev_deps = [
    ['rspec', '>=1.3.0'],
    ['yard', '>=0.5.3']
  ]
end

# vim: syntax=Ruby
