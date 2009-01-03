#
#--
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2009 Hal Brodigan (postmodern.mod3 at gmail.com)
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#++
#

require 'ronin/php/lfi/target'

module Ronin
  module PHP
    class LFI
      Target.config do |target|
        target.paths['Linux'] = ['/etc/passwd']
        target.paths['Solaris'] = ['/etc/passwd']

        target.recognizor = /root:/
      end

      Target.config do |target|
        target.paths['Linux'] = ['/etc/group']
        target.paths['Solaris'] = ['/etc/group']

        target.recognizor = /root:/
      end

      Target.config do |target|
        target.paths['Linux'] = ['/etc/fstab']
        target.paths['Solaris'] = ['/etc/vfstab']

        target.recognizor = /\/?proc\s+(-\s+)?\/proc\s+proc/
      end

      Target.config do |target|
        target.paths['Linux'] = ['/etc/mtab']
        target.paths['Solaris'] = ['/etc/mnttab']

        target.recognizor = /proc\s+\/proc\s+proc/
      end

      Target.config do |target|
        target.paths['Linux'] = ['/etc/apache/apache.conf', '/etc/apache2/apache.conf']
        target.paths['Solaris'] = ['/etc/apache/apache.conf', '/etc/apache2/apache.conf']

        target.recognizor = /ServerRoot/

        apache_setting = lambda { |name,setting|
          target.extract name, /^[^#]*#{setting}\s+\"?[^\"]+\"?\n/
        }

        apache_setting.call(:apache_server_name,'ServerName')
        apache_setting.call(:apache_server_listen,'Listen')
        apache_setting.call(:apache_server_bind,'BindAddress')
        apache_setting.call(:apache_server_port,'Port')
        apache_setting.call(:apache_server_root,'ServerRoot')
        apache_setting.call(:apache_server_admin,'ServerAdmin')
        apache_setting.call(:apache_document_root,'DocumentRoot')
        apache_setting.call(:apache_pid_file,'PidTarget')
        apache_setting.call(:apache_user,'User')
        apache_setting.call(:apache_group,'Group')
        apache_setting.call(:apache_log_level,'LogLevel')
        apache_setting.call(:apache_error_log,'ErrorLog')
        apache_setting.call(:apache_access_log,'CustomLog')
        apache_setting.call(:apache_access_filename,'AccessFileName')
        apache_setting.call(:apache_user_dir,'UserDir')
        apache_setting.call(:apache_script_alias,'ScriptAlias')
      end

      Target.config do |target|
        target.paths['Linux'] = ['/etc/lighttpd/lighttpd.conf']
        target.paths['Solaris'] = ['/etc/lighttpd/lighttpd.conf']

        target.recognizor = /server\.modules/

        lighttpd_string = lambda { |name,setting|
          target.extract name, /^[^#]*#{Regexp.escape(setting)}\s*=\s*\"([^\"]+)\"\n/
        }

        lighttpd_number = lambda { |name,setting|
          target.extract name, /^[^#]*#{Regexp.escape(setting)}\s*=\s*(\d+)\n/
        }

        lighttpd_string.call(:lighttpd_name,'server.name')
        lighttpd_string.call(:lighttpd_bind,'server.bind')
        lighttpd_number.call(:lighttpd_port,'server.port')
        lighttpd_string.call(:lighttpd_tag,'server.tag')
        lighttpd_string.call(:lighttpd_pid_file,'server.pid-file')
        lighttpd_string.call(:lighttpd_chroot,'server.chroot')
        lighttpd_string.call(:lighttpd_user,'server.username')
        lighttpd_string.call(:lighttpd_group,'server.groupname')
        lighttpd_string.call(:lighttpd_server_root,'server.root')
        lighttpd_string.call(:lighttpd_error_log,'server.errorlog')
        lighttpd_string.call(:lighttpd_access_log,'accesslog.filename')
        lighttpd_string.call(:lighttpd_auth,'auth.backend')
        lighttpd_string.call(:lighttpd_auth_plain_file,'auth.backend.plain.userfile')
        lighttpd_string.call(:lighttpd_auth_htpasswd_file,'auth.backend.htpasswd.userfile')
        lighttpd_string.call(:lighttpd_status_url,'status.status-url')
        lighttpd_string.call(:lighttpd_config_url,'status.config-url')
        lighttpd_string.call(:lighttpd_ssl,'ssl.engine')
        lighttpd_string.call(:lighttpd_ssl_pem,'ssl.pemfile')
      end

      Target.config do |target|
        target.paths['Linux'] = ['/etc/mysql/my.cnf']

        target.recognizor = /^\[mysql[^\]]*\]/

        mysql_setting = lambda { |name,setting|
          target.extract name, /\[mysqld\]\n[^\[]+#{setting}\s*=\s*(.*)\n/
        }

        mysql_setting.call(:mysql_user, 'user')
        mysql_setting.call(:mysql_port, 'port')
        mysql_setting.call(:mysql_socket, 'socket')
        mysql_setting.call(:mysql_log, 'log-error')
        mysql_setting.call(:mysql_data_dir, 'datadir')
        mysql_setting.call(:mysql_bind, 'bind-address')
      end
    end
  end
end
