require 'hpricot'
require 'cssmin'
require 'jsmin'

module Ronin
  module PHP
    #
    # Inlines and minifies any Javascript or CSS within the PHP file
    # at the specified _path_. The resulting minified PHP file will be saved
    # to the _output_ path.
    #
    def PHP.minify(path,output=nil)
      path = File.expand_path(path)
      dir = File.dirname(path)

      php_inline = lambda { |text|
        text.gsub(/<\?/,'<\?').gsub(/\?>/,'?\>')
      }

      doc = Hpricot(open(path))

      doc.search('//script[@type *= "javascript"]') do |script|
        js = ''

        if script.has_attribute?('src')
          js = File.open(File.join(dir,script.get_attribute('src')))
        else
          js = script.inner_text
        end

        script.swap("<script type=\"text/javascript\">" +
                    php_inline.call(JSMin.minify(js)) +
                    "</script>")
      end

      doc.search('//link[@rel="stylesheet"][@type="text/css"][@href]') do |link|
        css = File.read(File.join(dir,link.get_attribute('href')))

        link.swap("<style type=\"text/css\">" +
                  php_inline.call(CSSMin.minify(css)) +
                  "</style>")
      end

      ext = File.extname(path)
      output ||= path.gsub(/#{ext}$/,".min#{ext}")

      File.open(output,'w') do |min|
        min.write(doc.to_html)
      end

      return output
    end
  end
end
