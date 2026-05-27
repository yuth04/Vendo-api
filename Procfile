web: vendor/bin/heroku-php-apache2 public/
ai: gunicorn -w 2 -b 0.0.0.0:8000 python-ai.app:app