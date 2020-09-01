# This script will create the required tables for the web app

CREATE TABLE IF NOT EXISTS search (
 base_currency VARCHAR(3) NOT NULL ,
 start_date DATE NOT NULL,
 end_date DATE NOT NULL,
 created DATETIME NOT NULL,
 PRIMARY KEY (base_currency, start_date, end_date, created));

 CREATE TABLE IF NOT EXISTS exchange_rate (
  base_currency VARCHAR(3) NOT NULL,
  target_currency VARCHAR(3) NOT NULL,
  exchange_rate DOUBLE(20,10) NOT NULL,
  exchange_date DATE NOT NULL,
  PRIMARY KEY (base_currency, target_currency, exchange_date));
