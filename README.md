![QUIQQER Cache](bin/images/Readme.jpg)

Cache
========

Dieses Modul erweitert QUIQQER um ein besseres Webseiten-Caching-System. 
Das Cache-Modul generiert statisches HTML, kombiniert und optimiert CSS und JavaScript.

Paketname:

    quiqqer/cache


Features
--------

- Generiert statisches HTML
- Kombiniert CSS und JavaScript
- Optimiert CSS und JavaScript
- Verkleinert die Requests eines Templates
- Nutzt LocaleStorage für requireJS Aufrufe
- Optimiert JavaScript AMD Module
- Optimiert PNG und JPG Bilder beim Cache-Aufbau

- **WICHTIG**: Die JavaScript Komprimierung ist in der Beta.

Installation
------------

Der Paketname ist: quiqqer/cache



Mitwirken
----------

- Issue Tracker: https://dev.quiqqer.com/quiqqer/package-cache/issues
- Source Code: https://dev.quiqqer.com/quiqqer/package-cache/tree/master


Support
-------

Falls Sie Fehler gefunden, Wünsche oder Verbesserungsvorschläge haben, 
können Sie uns gern per Mail an support@pcsg.de darüber informieren.  
Wir werden versuchen auf Ihre Wünsche einzugehen bzw. diese an die 
zuständigen Entwickler des Projektes weiterleiten.

License
-------


Entwickler
--------

*Needle*
- jpegoptim
- optipng
- npm für die AMD Komprimierung
- uglifyjs

*Konsole - Bilder optimieren*

```
php quiqqer.php --username=* --password=* --tool=package:cache-optimize --project=*
```


@todo 
http://www.webmaster-zentrale.de/technik/optimierung/webseiten-beschleunigen-teil-2-expires-header-verwenden/
