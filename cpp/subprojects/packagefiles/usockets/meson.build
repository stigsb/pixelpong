project('usockets', 'c', 'cpp', version: '0.8.8')

# uSockets needs either OpenSSL or no-SSL mode
# Use no-SSL for simplicity (game doesn't need TLS)
zlib_dep = dependency('zlib', fallback: ['zlib', 'zlib_dep'])

src = files(
  'src/bsd.c',
  'src/context.c',
  'src/loop.c',
  'src/socket.c',
  'src/eventing/epoll_kqueue.c',
  'src/udp.c',
)

# No SSL - simpler build
usockets_lib = static_library('usockets',
  src,
  c_args: ['-DLIBUS_NO_SSL'],
  dependencies: [zlib_dep],
  include_directories: include_directories('src'),
)

usockets_dep = declare_dependency(
  link_with: usockets_lib,
  include_directories: include_directories('src'),
  dependencies: [zlib_dep],
)
