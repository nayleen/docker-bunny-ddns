# Changelog

## 1.0.0 (2026-07-23)


### Features

* better env parsing, auto-create zones if configured but missing (with opt-out) ([65d4c8f](https://github.com/nayleen/docker-bunny-ddns/commit/65d4c8fd500c81aa1fc452a1ff683925a66397e9))
* first basic implementation ([116cdb6](https://github.com/nayleen/docker-bunny-ddns/commit/116cdb6b68e6a52568d8138866dbd5eb517f9f17))


### Bug Fixes

* **ci:** actually use locally built image ([ed810e7](https://github.com/nayleen/docker-bunny-ddns/commit/ed810e72066a70fde5c85a2c1a08217d7a108490))
* **ci:** actually use locally built image ([5ccd737](https://github.com/nayleen/docker-bunny-ddns/commit/5ccd737178e33007ba722cb8605ef148892bb010))
* **ci:** actually use locally built image ([838db37](https://github.com/nayleen/docker-bunny-ddns/commit/838db37219f4425b2f05287a7c056056f98c15dd))
* **ci:** copy .env example file ([98c181b](https://github.com/nayleen/docker-bunny-ddns/commit/98c181b84d5c5148124b89cc710e0a8d7f4bf00b))
* **ci:** invalid local build ref ([6ef4eeb](https://github.com/nayleen/docker-bunny-ddns/commit/6ef4eeb42bd302e14d3baf037d763253d6f84fd7))
* don't accept ips ending with .0 as valid ([c54f69f](https://github.com/nayleen/docker-bunny-ddns/commit/c54f69f5cdff24ea98b4674903afd4341ca031a5))
* harden DNS update error handling and validation ([b4bf16d](https://github.com/nayleen/docker-bunny-ddns/commit/b4bf16d8dd4900c0c0e4bed35d6caf1e5f27cb42))
* only update current ip if all zone updates succeed ([bcb657c](https://github.com/nayleen/docker-bunny-ddns/commit/bcb657cf37c06ba9cc10127ceb3e02393b503577))
* parse both  and ([5e82b2d](https://github.com/nayleen/docker-bunny-ddns/commit/5e82b2d9b0c42e4bfa110d77c84abb949b1a369e))
* run ci on app.php changes ([d48b540](https://github.com/nayleen/docker-bunny-ddns/commit/d48b5405d3f135d72593dc6f77ffab9c54d6272d))
* use optimized autoloader ([11d4c5e](https://github.com/nayleen/docker-bunny-ddns/commit/11d4c5e2be430677813e5ce5174d357a72cea6da))
* vendor/ permissions after upstream changes ([f5da53b](https://github.com/nayleen/docker-bunny-ddns/commit/f5da53b4d684c0c7f61aec95aa7782e00125afa6))


### Miscellaneous Chores

* add license file and document usage ([b2dde0f](https://github.com/nayleen/docker-bunny-ddns/commit/b2dde0fba0bc7a0769d3385d4034d2267a869a6c))
* add release-please for tagged releases instead of "latest" ([f3676bf](https://github.com/nayleen/docker-bunny-ddns/commit/f3676bf1ca27d59aa18200bfe6817853e3e0d43b))
* add Taskfile for local development ([1ae22f1](https://github.com/nayleen/docker-bunny-ddns/commit/1ae22f1ac1122338c4c523c8ee096478b2c18cc7))
* bump deps ([a1543b4](https://github.com/nayleen/docker-bunny-ddns/commit/a1543b4ec337b7537dc902875da8f3e05afb05a4))
* bump deps ([5216f8d](https://github.com/nayleen/docker-bunny-ddns/commit/5216f8d1ba501c0a7d47835a7cb3a65f9bbc9368))
* **ci:** remove intermediate containers ([702f5ce](https://github.com/nayleen/docker-bunny-ddns/commit/702f5ce9bf72af5d4c6f6da2f9bf7167e07b7988))
* debug docker swarm env ([b2afa6d](https://github.com/nayleen/docker-bunny-ddns/commit/b2afa6d537174f1524543ac4377a82527d76772a))
* **deps-dev:** bump friendsofphp/php-cs-fixer from 3.92.4 to 3.93.0 ([e97073d](https://github.com/nayleen/docker-bunny-ddns/commit/e97073dc452045828d58f281238481e94eae966f))
* **deps:** bump ([bd0084d](https://github.com/nayleen/docker-bunny-ddns/commit/bd0084d62812abb82adc237cbac764014076e434))
* **deps:** bump actions/checkout in the github-actions group ([03511cd](https://github.com/nayleen/docker-bunny-ddns/commit/03511cd62a528c0c7c737f948fff927d119a9bab))
* **deps:** bump amphp/file from 3.2.0 to 4.0.0 ([73e03e7](https://github.com/nayleen/docker-bunny-ddns/commit/73e03e76b5f31df939a9d9e300750a64e4798e2c))
* **deps:** bump dev dependencies ([c1f2a8a](https://github.com/nayleen/docker-bunny-ddns/commit/c1f2a8abd8161abae6098485b2cbad1a86251568))
* **deps:** bump the github-actions group across 1 directory with 4 updates ([cf42859](https://github.com/nayleen/docker-bunny-ddns/commit/cf42859537bc3a56aeef5a2ea7cf209a7f29714a))
* initial commit ([b85bf1c](https://github.com/nayleen/docker-bunny-ddns/commit/b85bf1ce12b188fb2ea175b6caeb09a76c20556c))
* update list of ip resolver service urls ([9bc5577](https://github.com/nayleen/docker-bunny-ddns/commit/9bc5577f961556cd4355d32a0bd9baecd2cdd1d4))
* update to php8.5 + ffi ([c562bed](https://github.com/nayleen/docker-bunny-ddns/commit/c562bed4dce0441323563d42de67a2432ae7c91c))
