#!/bin/sh
# Installs the toolchain mise.toml pins: Composer, and PHP built from source.
#
# Usage: scripts/install-toolchain.sh   (from the repository root)
#
# mise builds PHP from its source release through the vfox-php plugin, which
# reads its configure options from this process's environment, so they are
# set here rather than in mise.toml. The build is configured with only the
# extensions the package and its tools use: the core ones (random, json,
# hash, pcre, spl, date), plus ctype, dom, filter, mbstring, phar,
# tokenizer, xml, xmlreader, xmlwriter, simplexml, fileinfo, posix, iconv, libxml,
# openssl, zlib and PEAR. After PHP installs, mise runs
# `pecl install pcov` (the postinstall in mise.toml) for the gate's
# line coverage.
#
# The build needs a C compiler, autoconf, bison 3 or later, re2c, pkg-config,
# libxml2 and OpenSSL. On macOS these come from Homebrew (bison, libxml2,
# openssl@3, re2c, pkgconf); bison and libxml2 are keg-only there, and the
# system bison is too old, so YACC names Homebrew's bison and the plugin adds
# Homebrew's pkg-config paths itself. Elsewhere, set YACC to a bison 3 or
# leave it unset to use the one on PATH.
set -eu

if [ -z "${YACC:-}" ] && [ -x /opt/homebrew/opt/bison/bin/bison ]; then
  YACC=/opt/homebrew/opt/bison/bin/bison
  export YACC
fi

# macOS keeps the iconv headers in the SDK, not under /usr/include.
ICONV_DIR=""
if [ "$(uname -s)" = Darwin ]; then
  ICONV_DIR="$(xcrun --show-sdk-path)/usr"
fi

PHP_CONFIGURE_OPTIONS="--disable-all --enable-ctype --enable-dom --enable-filter --enable-mbstring --disable-mbregex --enable-phar --enable-tokenizer --enable-xml --enable-xmlreader --enable-xmlwriter --enable-simplexml --enable-fileinfo --enable-posix --with-iconv${ICONV_DIR:+=$ICONV_DIR} --with-libxml --with-openssl --with-zlib --with-pear --without-pcre-jit"
export PHP_CONFIGURE_OPTIONS

exec mise install "$@"
