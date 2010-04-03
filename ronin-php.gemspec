# Generated by jeweler
# DO NOT EDIT THIS FILE DIRECTLY
# Instead, edit Jeweler::Tasks in Rakefile, and run the gemspec command
# -*- encoding: utf-8 -*-

Gem::Specification.new do |s|
  s.name = %q{ronin-php}
  s.version = "0.2.0"

  s.required_rubygems_version = Gem::Requirement.new(">= 0") if s.respond_to? :required_rubygems_version=
  s.authors = ["Postmodern"]
  s.date = %q{2010-04-03}
  s.default_executable = %q{ronin-php}
  s.description = %q{Ronin PHP is a Ruby library for Ronin that provides support for PHP related security tasks.}
  s.email = %q{postmodern.mod3@gmail.com}
  s.executables = ["ronin-php"]
  s.extra_rdoc_files = [
    "ChangeLog.md",
    "README.md"
  ]
  s.files = [
    ".gitignore",
    ".specopts",
    ".yardopts",
    "COPYING.txt",
    "ChangeLog.md",
    "README.md",
    "Rakefile",
    "bin/ronin-php",
    "lib/ronin/exploits/php.rb",
    "lib/ronin/exploits/php/lfi.rb",
    "lib/ronin/exploits/php/rfi.rb",
    "lib/ronin/gen/generators/php/rpc_server.rb",
    "lib/ronin/payloads/php.rb",
    "lib/ronin/payloads/php/backdoor.rb",
    "lib/ronin/payloads/php/rpc.rb",
    "lib/ronin/php.rb",
    "lib/ronin/php/config.rb",
    "lib/ronin/php/extensions.rb",
    "lib/ronin/php/extensions/string.rb",
    "lib/ronin/php/lfi.rb",
    "lib/ronin/php/lfi/exceptions.rb",
    "lib/ronin/php/lfi/exceptions/unknown_target.rb",
    "lib/ronin/php/lfi/extensions.rb",
    "lib/ronin/php/lfi/extensions/uri.rb",
    "lib/ronin/php/lfi/extensions/uri/http.rb",
    "lib/ronin/php/lfi/file.rb",
    "lib/ronin/php/lfi/lfi.rb",
    "lib/ronin/php/lfi/scanner.rb",
    "lib/ronin/php/lfi/target.rb",
    "lib/ronin/php/lfi/targets.rb",
    "lib/ronin/php/lfi/targets/configs.rb",
    "lib/ronin/php/lfi/targets/logs.rb",
    "lib/ronin/php/lfi/targets/tests.rb",
    "lib/ronin/php/rfi.rb",
    "lib/ronin/php/rfi/extensions.rb",
    "lib/ronin/php/rfi/extensions/uri.rb",
    "lib/ronin/php/rfi/extensions/uri/http.rb",
    "lib/ronin/php/rfi/rfi.rb",
    "lib/ronin/php/rfi/scanner.rb",
    "lib/ronin/php/version.rb",
    "lib/ronin/rpc/php.rb",
    "lib/ronin/rpc/php/call.rb",
    "lib/ronin/rpc/php/client.rb",
    "lib/ronin/rpc/php/console.rb",
    "lib/ronin/rpc/php/response.rb",
    "lib/ronin/rpc/php/rfi.rb",
    "lib/ronin/rpc/php/shell.rb",
    "ronin-php.gemspec",
    "spec/exploits/php/helpers/database.rb",
    "spec/helpers/database.rb",
    "spec/php/php_spec.rb",
    "spec/php/rfi_spec.rb",
    "spec/rpc/php/rfi_spec.rb",
    "spec/spec_helper.rb",
    "static/ronin/gen/php/rpc/ajax.php.erb",
    "static/ronin/gen/php/rpc/ajax/css/layout.css",
    "static/ronin/gen/php/rpc/ajax/js/base64.js",
    "static/ronin/gen/php/rpc/ajax/js/jquery-ui-personalized.min.js",
    "static/ronin/gen/php/rpc/ajax/js/jquery.min.js",
    "static/ronin/gen/php/rpc/ajax/js/jquery.phprpc.js",
    "static/ronin/gen/php/rpc/ajax/js/jquery.terminal.js",
    "static/ronin/gen/php/rpc/ajax/js/ui.js",
    "static/ronin/gen/php/rpc/console_service.php",
    "static/ronin/gen/php/rpc/rpc_server.php",
    "static/ronin/gen/php/rpc/server.php.erb",
    "static/ronin/gen/php/rpc/service.php",
    "static/ronin/gen/php/rpc/shell_service.php",
    "static/ronin/php/rfi/backdoor.php",
    "static/ronin/php/rfi/test.php",
    "static/ronin/php/rpc/server.php"
  ]
  s.has_rdoc = %q{yard}
  s.homepage = %q{http://github.com/ronin-ruby/ronin-php}
  s.licenses = ["GPL-2"]
  s.rdoc_options = ["--charset=UTF-8"]
  s.require_paths = ["lib"]
  s.rubygems_version = %q{1.3.6}
  s.summary = %q{A Ruby library for Ronin that provides support for PHP related security tasks.}
  s.test_files = [
    "spec/spec_helper.rb",
    "spec/rpc/php/rfi_spec.rb",
    "spec/php/php_spec.rb",
    "spec/php/rfi_spec.rb",
    "spec/helpers/database.rb",
    "spec/exploits/php/helpers/database.rb"
  ]

  if s.respond_to? :specification_version then
    current_version = Gem::Specification::CURRENT_SPECIFICATION_VERSION
    s.specification_version = 3

    if Gem::Version.new(Gem::RubyGemsVersion) >= Gem::Version.new('1.2.0') then
      s.add_runtime_dependency(%q<cssmin>, ["~> 1.0.2"])
      s.add_runtime_dependency(%q<jsmin>, ["~> 1.0.1"])
      s.add_runtime_dependency(%q<ronin>, ["~> 0.4.0"])
      s.add_runtime_dependency(%q<ronin-web>, ["~> 0.2.2"])
      s.add_runtime_dependency(%q<ronin-exploits>, ["~> 0.3.2"])
      s.add_development_dependency(%q<rspec>, ["~> 1.3.0"])
      s.add_development_dependency(%q<yard>, ["~> 0.5.3"])
    else
      s.add_dependency(%q<cssmin>, ["~> 1.0.2"])
      s.add_dependency(%q<jsmin>, ["~> 1.0.1"])
      s.add_dependency(%q<ronin>, ["~> 0.4.0"])
      s.add_dependency(%q<ronin-web>, ["~> 0.2.2"])
      s.add_dependency(%q<ronin-exploits>, ["~> 0.3.2"])
      s.add_dependency(%q<rspec>, ["~> 1.3.0"])
      s.add_dependency(%q<yard>, ["~> 0.5.3"])
    end
  else
    s.add_dependency(%q<cssmin>, ["~> 1.0.2"])
    s.add_dependency(%q<jsmin>, ["~> 1.0.1"])
    s.add_dependency(%q<ronin>, ["~> 0.4.0"])
    s.add_dependency(%q<ronin-web>, ["~> 0.2.2"])
    s.add_dependency(%q<ronin-exploits>, ["~> 0.3.2"])
    s.add_dependency(%q<rspec>, ["~> 1.3.0"])
    s.add_dependency(%q<yard>, ["~> 0.5.3"])
  end
end

