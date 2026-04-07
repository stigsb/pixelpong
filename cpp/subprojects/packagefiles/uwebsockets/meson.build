project('uwebsockets', 'cpp', version: '20.71.0')

usockets_dep = dependency('usockets', fallback: ['usockets', 'usockets_dep'])

uwebsockets_dep = declare_dependency(
  include_directories: include_directories('src'),
  dependencies: [usockets_dep],
)
