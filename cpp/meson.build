project('pixelpong', 'cpp', 'c',
  version: '0.1.0',
  default_options: [
    'cpp_std=c++23',
    'warning_level=2',
  ],
)

nlohmann_json_dep = dependency('nlohmann_json', fallback: ['nlohmann_json', 'nlohmann_json_dep'])
uwebsockets_dep = dependency('uwebsockets', fallback: ['uwebsockets', 'uwebsockets_dep'])
stb_dep = dependency('stb', fallback: ['stb', 'stb_dep'])

pixelpong_inc = include_directories('src')

executable('pixelpong',
  'src/main.cpp',
  include_directories: pixelpong_inc,
  dependencies: [nlohmann_json_dep, uwebsockets_dep, stb_dep],
  install: true,
)
