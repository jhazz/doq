-- --------------------------------------------------------
-- Хост:                         127.0.0.1
-- Версия сервера:               5.6.23-log - MySQL Community Server (GPL)
-- ОС Сервера:                   Win32
-- HeidiSQL Версия:              9.1.0.4867
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Дамп структуры базы данных test
DROP DATABASE IF EXISTS `test`;
CREATE DATABASE IF NOT EXISTS `test` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `test`;


-- Дамп структуры для таблица test.components
DROP TABLE IF EXISTS `components`;
CREATE TABLE IF NOT EXISTS `components` (
  `COMPONENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PART_OF_COMPONENT_ID` int(11) DEFAULT NULL,
  `NAME` varchar(50) DEFAULT NULL,
  `DESCRIPTION` varchar(150) DEFAULT NULL,
  `LAST_SOURCE_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`COMPONENT_ID`),
  KEY `FK_components_component_sources` (`LAST_SOURCE_ID`),
  CONSTRAINT `FK_components_component_sources` FOREIGN KEY (`LAST_SOURCE_ID`) REFERENCES `component_sources` (`COMPONENT_SOURCE_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.components: ~17 rows (приблизительно)
DELETE FROM `components`;
/*!40000 ALTER TABLE `components` DISABLE KEYS */;
INSERT INTO `components` (`COMPONENT_ID`, `PART_OF_COMPONENT_ID`, `NAME`, `DESCRIPTION`, `LAST_SOURCE_ID`) VALUES
	(1, NULL, 'О.С Модуль 16х32-YH', 'Модуль светодиодный 16х32, Yellow, High', 2),
	(2, NULL, 'О.С Модуль 8х8-R', 'Модуль светодиодный 8x8, Red', 1),
	(3, NULL, 'О.С Модуль 8х8-RG', 'Модуль светодиодный 8x8, Red+Green', 1),
	(4, NULL, 'О.С indik_ctrl_v1.0', 'Контроллер для матриц 8x8', 3),
	(5, NULL, 'О.С БП для модулей 16х32 LED PWR', 'Блок питания для модулей 16х32', 3),
	(6, NULL, 'О.С indik_ctrl  для матриц 16x32', 'Контроллер для матриц 16x32', 3),
	(7, NULL, 'О.С indikator1(8x8x5) v1.1b', 'Плата для 5 матриц 8х8', 3),
	(8, NULL, 'О.С RunStr_RG_v1.3', 'Плата для 6 матриц RG 8х8', 3),
	(9, NULL, 'О.Р Д3-4.1-А в баз.комп', 'ОР Д3-4.1-А базовая комплектация', 4),
	(10, NULL, 'О.Р 3G PCI-e модем', 'ОР Huawei 3G PCI-e', NULL),
	(11, NULL, 'О.Р Антенна 3G/GSM', 'О.Р Антенна 3G/GSM', NULL),
	(12, NULL, 'О.Р Антенна GNSS', 'О.Р Антенна GNSS', NULL),
	(13, NULL, 'О.Р скоба для 3G', 'О.Р скоба для уст 3G PCI-e модема', NULL),
	(14, NULL, 'О.Р UFL-SMA 20', 'О.Р UFL-SMA удлинитель для 3G модуля 20 см', NULL),
	(15, NULL, 'Винтик для 3G', 'Винтик для 3G', NULL),
	(16, NULL, 'Резина теплопроводящая', 'Резина теплопроводящая', 6),
	(17, NULL, 'СН-О.С-16-64', 'Станд.набор О.С-16-64', NULL);
/*!40000 ALTER TABLE `components` ENABLE KEYS */;


-- Дамп структуры для таблица test.component_aggregates
DROP TABLE IF EXISTS `component_aggregates`;
CREATE TABLE IF NOT EXISTS `component_aggregates` (
  `COMPONENT_AGGREGATE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PARENT_COMPONENT_ID` int(11) NOT NULL DEFAULT '0',
  `CHILD_COMPONENT_ID` int(11) NOT NULL DEFAULT '0',
  `CHILD_AMOUNT` double NOT NULL DEFAULT '0',
  `DETAILS` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`COMPONENT_AGGREGATE_ID`),
  KEY `FK_component_aggregate_components` (`PARENT_COMPONENT_ID`),
  KEY `FK_component_aggregate_components_2` (`CHILD_COMPONENT_ID`),
  CONSTRAINT `FK_component_aggregate_components` FOREIGN KEY (`PARENT_COMPONENT_ID`) REFERENCES `components` (`COMPONENT_ID`),
  CONSTRAINT `FK_component_aggregate_components_2` FOREIGN KEY (`CHILD_COMPONENT_ID`) REFERENCES `components` (`COMPONENT_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.component_aggregates: ~3 rows (приблизительно)
DELETE FROM `component_aggregates`;
/*!40000 ALTER TABLE `component_aggregates` DISABLE KEYS */;
INSERT INTO `component_aggregates` (`COMPONENT_AGGREGATE_ID`, `PARENT_COMPONENT_ID`, `CHILD_COMPONENT_ID`, `CHILD_AMOUNT`, `DETAILS`) VALUES
	(1, 17, 5, 2, 'По-ум. 2 БП'),
	(2, 17, 1, 2, '2 мод 1632'),
	(3, 17, 6, 1, '1 контрол');
/*!40000 ALTER TABLE `component_aggregates` ENABLE KEYS */;


-- Дамп структуры для таблица test.component_sources
DROP TABLE IF EXISTS `component_sources`;
CREATE TABLE IF NOT EXISTS `component_sources` (
  `COMPONENT_SOURCE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `MASS_NAME` varchar(50) DEFAULT NULL,
  `SPECIAL_NAME` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`COMPONENT_SOURCE_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.component_sources: ~6 rows (приблизительно)
DELETE FROM `component_sources`;
/*!40000 ALTER TABLE `component_sources` DISABLE KEYS */;
INSERT INTO `component_sources` (`COMPONENT_SOURCE_ID`, `MASS_NAME`, `SPECIAL_NAME`) VALUES
	(1, 'Киты 8х8', 'Поставщик Матриц 8х8'),
	(2, 'Киты 16х32', 'Поставщик Матриц 16х32'),
	(3, 'Цех', 'Линия SMD'),
	(4, 'Киты ОР', 'Поставщик видеокомпл'),
	(5, 'Киты антенн', NULL),
	(6, 'Киты россыпь', NULL);
/*!40000 ALTER TABLE `component_sources` ENABLE KEYS */;


-- Дамп структуры для таблица test.config_components
DROP TABLE IF EXISTS `config_components`;
CREATE TABLE IF NOT EXISTS `config_components` (
  `CONFIG_COMPONENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_CONFIG_ID` int(11) DEFAULT NULL,
  `COMPONENT_ID` int(11) DEFAULT NULL,
  `DELTA_AMOUNT` double DEFAULT NULL,
  PRIMARY KEY (`CONFIG_COMPONENT_ID`),
  KEY `FK_config_components_product_configs` (`PRODUCT_CONFIG_ID`),
  KEY `FK_config_components_components` (`COMPONENT_ID`),
  CONSTRAINT `FK_config_components_components` FOREIGN KEY (`COMPONENT_ID`) REFERENCES `components` (`COMPONENT_ID`),
  CONSTRAINT `FK_config_components_product_configs` FOREIGN KEY (`PRODUCT_CONFIG_ID`) REFERENCES `product_configs` (`PRODUCT_CONFIG_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.config_components: ~0 rows (приблизительно)
DELETE FROM `config_components`;
/*!40000 ALTER TABLE `config_components` DISABLE KEYS */;
/*!40000 ALTER TABLE `config_components` ENABLE KEYS */;


-- Дамп структуры для таблица test.currencies
DROP TABLE IF EXISTS `currencies`;
CREATE TABLE IF NOT EXISTS `currencies` (
  `CURRENCY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(50) NOT NULL,
  PRIMARY KEY (`CURRENCY_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.currencies: ~3 rows (приблизительно)
DELETE FROM `currencies`;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` (`CURRENCY_ID`, `NAME`) VALUES
	(1, '$'),
	(2, 'руб'),
	(3, 'CNY');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;


-- Дамп структуры для таблица test.customers
DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `CUSTOMER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(150) NOT NULL,
  `PLACE_ID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`CUSTOMER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.customers: ~0 rows (приблизительно)
DELETE FROM `customers`;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` (`CUSTOMER_ID`, `NAME`, `PLACE_ID`) VALUES
	(2, 'ТТМ', 0);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;


-- Дамп структуры для таблица test.cust_persons
DROP TABLE IF EXISTS `cust_persons`;
CREATE TABLE IF NOT EXISTS `cust_persons` (
  `CUST_PERSON_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PERSON_ID` int(11) DEFAULT NULL,
  `CUSTOMER_ID` int(11) DEFAULT NULL,
  `DATE_BEGIN` date DEFAULT NULL,
  `DATE_END` date DEFAULT NULL,
  PRIMARY KEY (`CUST_PERSON_ID`),
  KEY `FK_cust_persons_customers` (`CUSTOMER_ID`),
  KEY `FK_cust_persons_persons` (`PERSON_ID`),
  CONSTRAINT `FK_cust_persons_customers` FOREIGN KEY (`CUSTOMER_ID`) REFERENCES `customers` (`CUSTOMER_ID`),
  CONSTRAINT `FK_cust_persons_persons` FOREIGN KEY (`PERSON_ID`) REFERENCES `sys_persons` (`PERSON_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.cust_persons: ~3 rows (приблизительно)
DELETE FROM `cust_persons`;
/*!40000 ALTER TABLE `cust_persons` DISABLE KEYS */;
INSERT INTO `cust_persons` (`CUST_PERSON_ID`, `PERSON_ID`, `CUSTOMER_ID`, `DATE_BEGIN`, `DATE_END`) VALUES
	(1, 2, 2, '2016-02-01', NULL),
	(2, 3, 2, '2016-02-01', NULL),
	(3, 5, 2, '2016-02-01', NULL);
/*!40000 ALTER TABLE `cust_persons` ENABLE KEYS */;


-- Дамп структуры для таблица test.geo_points
DROP TABLE IF EXISTS `geo_points`;
CREATE TABLE IF NOT EXISTS `geo_points` (
  `GEO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `POSITION` point DEFAULT NULL,
  PRIMARY KEY (`GEO_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.geo_points: ~0 rows (приблизительно)
DELETE FROM `geo_points`;
/*!40000 ALTER TABLE `geo_points` DISABLE KEYS */;
/*!40000 ALTER TABLE `geo_points` ENABLE KEYS */;


-- Дамп структуры для таблица test.geo_routes
DROP TABLE IF EXISTS `geo_routes`;
CREATE TABLE IF NOT EXISTS `geo_routes` (
  `ROUTE_IT` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`ROUTE_IT`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.geo_routes: 0 rows
DELETE FROM `geo_routes`;
/*!40000 ALTER TABLE `geo_routes` DISABLE KEYS */;
/*!40000 ALTER TABLE `geo_routes` ENABLE KEYS */;


-- Дамп структуры для таблица test.orders
DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `ORDER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) NOT NULL DEFAULT '0',
  `CUST_PERSON_ID` int(11) NOT NULL DEFAULT '0',
  `CUSTOMER_ID` int(11) NOT NULL DEFAULT '0',
  `PROJECT_ID` int(11) NOT NULL DEFAULT '0',
  `OPEN_DATE` date DEFAULT NULL,
  `SHIPPING_PLAN_DATE` date DEFAULT NULL,
  `NAME` varchar(120) DEFAULT NULL,
  `ORDER_PAYMENT_STATUS_ID` int(11) DEFAULT NULL,
  `COMMENTS` text,
  `ORDER_READY_STATUS_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  KEY `FK_orders_users` (`USER_ID`),
  KEY `FK_orders_customers` (`CUSTOMER_ID`),
  CONSTRAINT `FK_orders_customers` FOREIGN KEY (`CUSTOMER_ID`) REFERENCES `customers` (`CUSTOMER_ID`),
  CONSTRAINT `FK_orders_users` FOREIGN KEY (`USER_ID`) REFERENCES `sys_users` (`USER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.orders: ~5 rows (приблизительно)
DELETE FROM `orders`;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` (`ORDER_ID`, `USER_ID`, `CUST_PERSON_ID`, `CUSTOMER_ID`, `PROJECT_ID`, `OPEN_DATE`, `SHIPPING_PLAN_DATE`, `NAME`, `ORDER_PAYMENT_STATUS_ID`, `COMMENTS`, `ORDER_READY_STATUS_ID`) VALUES
	(1, 1, 1, 2, 1, '2016-01-07', '2016-01-28', 'ИВ-СВ/2016-01', 2, NULL, 3),
	(2, 1, 2, 2, 1, '2016-01-07', '2016-02-04', 'ИВ-СВ/2016-02', 1, NULL, NULL),
	(6, 1, 2, 2, 1, '2016-01-08', '2016-02-11', 'ИВ-СВ/2016-03', NULL, NULL, NULL),
	(9, 1, 3, 2, 2, '2016-01-11', '2016-02-17', 'МАЗ-БВ-ТКЛ/2016-01', NULL, 'Бутаков: Спецификация будет готова СКОРО!', NULL),
	(10, 1, 3, 2, 3, '2016-01-12', '2016-02-18', 'ИВ-МВ/2016-01', NULL, NULL, NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;


-- Дамп структуры для таблица test.order_item
DROP TABLE IF EXISTS `order_item`;
CREATE TABLE IF NOT EXISTS `order_item` (
  `ORDER_ITEM_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` int(11) NOT NULL,
  `ORDER_ID` int(11) NOT NULL,
  `AMOUNT` float NOT NULL,
  `DESCRIPTION` varchar(150) NOT NULL,
  PRIMARY KEY (`ORDER_ITEM_ID`),
  KEY `FK_order_item_products` (`PRODUCT_ID`),
  KEY `FK_order_item_orders` (`ORDER_ID`),
  CONSTRAINT `FK_order_item_orders` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`),
  CONSTRAINT `FK_order_item_products` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRODUCT_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.order_item: ~38 rows (приблизительно)
DELETE FROM `order_item`;
/*!40000 ALTER TABLE `order_item` DISABLE KEYS */;
INSERT INTO `order_item` (`ORDER_ITEM_ID`, `PRODUCT_ID`, `ORDER_ID`, `AMOUNT`, `DESCRIPTION`) VALUES
	(2, 14, 1, 25, 'табло лобовое большое'),
	(3, 15, 1, 25, 'табло боковое'),
	(6, 16, 1, 25, 'табло заднее'),
	(7, 22, 1, 25, 'табло салонное'),
	(8, 21, 1, 25, 'видеорегистратор'),
	(9, 20, 1, 25, 'ИБП'),
	(10, 19, 1, 25, 'АИ 3.0'),
	(11, 13, 1, 25, 'моноблок'),
	(12, 14, 2, 25, 'табло лобовое большое'),
	(13, 15, 2, 25, 'табло боковое'),
	(14, 16, 2, 25, 'табло заднее'),
	(15, 22, 2, 25, 'табло салонное'),
	(16, 21, 2, 25, 'видеорегистратор'),
	(17, 20, 2, 25, 'ИБП'),
	(18, 19, 2, 25, 'АИ 3.0'),
	(19, 13, 2, 25, 'моноблок'),
	(20, 14, 6, 25, 'табло лобовое большое'),
	(21, 15, 6, 25, 'табло боковое'),
	(22, 16, 6, 25, 'табло заднее'),
	(23, 22, 6, 25, 'табло салонное'),
	(24, 21, 6, 25, 'видеорегистратор'),
	(25, 20, 6, 25, 'ИБП'),
	(26, 19, 6, 25, 'АИ 3.0'),
	(27, 13, 6, 25, 'моноблок'),
	(28, 23, 9, 68, 'табло лобовое'),
	(29, 25, 9, 68, 'табло боковое'),
	(30, 26, 9, 68, 'табло заднее'),
	(31, 28, 9, 68, 'табло салонное'),
	(32, 27, 9, 68, ''),
	(33, 29, 10, 50, 'табло лобовое'),
	(34, 31, 10, 50, 'табло боковое малое'),
	(35, 32, 10, 50, 'табло заднее'),
	(36, 30, 10, 50, 'табло боковое'),
	(37, 33, 10, 50, 'табло салонное'),
	(38, 13, 10, 50, 'моноблок'),
	(39, 19, 10, 50, 'АИ 3.0'),
	(40, 21, 10, 50, 'видеорегистратор'),
	(41, 20, 10, 50, 'ИБП');
/*!40000 ALTER TABLE `order_item` ENABLE KEYS */;


-- Дамп структуры для таблица test.order_payment_statuses
DROP TABLE IF EXISTS `order_payment_statuses`;
CREATE TABLE IF NOT EXISTS `order_payment_statuses` (
  `ORDER_PAYMENT_STATUS_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STATUS_LABEL` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ORDER_PAYMENT_STATUS_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.order_payment_statuses: ~4 rows (приблизительно)
DELETE FROM `order_payment_statuses`;
/*!40000 ALTER TABLE `order_payment_statuses` DISABLE KEYS */;
INSERT INTO `order_payment_statuses` (`ORDER_PAYMENT_STATUS_ID`, `STATUS_LABEL`) VALUES
	(1, '1.Прогноз заказа'),
	(2, '2.Ожидается поступление средств'),
	(3, '3.Частично оплачено'),
	(4, '4.Оплачено полностью');
/*!40000 ALTER TABLE `order_payment_statuses` ENABLE KEYS */;


-- Дамп структуры для таблица test.order_ready_statuses
DROP TABLE IF EXISTS `order_ready_statuses`;
CREATE TABLE IF NOT EXISTS `order_ready_statuses` (
  `ORDER_READY_STATUS_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`ORDER_READY_STATUS_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.order_ready_statuses: ~6 rows (приблизительно)
DELETE FROM `order_ready_statuses`;
/*!40000 ALTER TABLE `order_ready_statuses` DISABLE KEYS */;
INSERT INTO `order_ready_statuses` (`ORDER_READY_STATUS_ID`, `NAME`) VALUES
	(1, '1.Оценка стоимости'),
	(2, '2.Закупка'),
	(3, '3.Изготовление'),
	(4, '4.Отправлено'),
	(5, '5.Получено заказчиком'),
	(6, '1.1 Отклонено заказчиком');
/*!40000 ALTER TABLE `order_ready_statuses` ENABLE KEYS */;


-- Дамп структуры для таблица test.parameters
DROP TABLE IF EXISTS `parameters`;
CREATE TABLE IF NOT EXISTS `parameters` (
  `PARAMETER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PARAMETER_GROUP_ID` int(11) DEFAULT NULL,
  `NAME` varchar(100) DEFAULT NULL,
  `UNITS` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`PARAMETER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.parameters: ~6 rows (приблизительно)
DELETE FROM `parameters`;
/*!40000 ALTER TABLE `parameters` DISABLE KEYS */;
INSERT INTO `parameters` (`PARAMETER_ID`, `PARAMETER_GROUP_ID`, `NAME`, `UNITS`) VALUES
	(1, 1, 'Количество камер', 'шт'),
	(2, 1, 'Количество IP камер', 'шт'),
	(3, 2, 'Питание', 'В'),
	(4, 1, 'HDD кейс', 'наличие'),
	(5, 2, 'Вес', 'г'),
	(6, 2, 'Ширина', 'мм');
/*!40000 ALTER TABLE `parameters` ENABLE KEYS */;


-- Дамп структуры для таблица test.prices
DROP TABLE IF EXISTS `prices`;
CREATE TABLE IF NOT EXISTS `prices` (
  `PRICE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CURRENCY_ID` int(11) NOT NULL,
  `PRODUCT_ID` int(11) NOT NULL,
  `VALUE` decimal(10,2) NOT NULL COMMENT 'себестоимость',
  PRIMARY KEY (`PRICE_ID`),
  KEY `FK_prices_currencies` (`CURRENCY_ID`),
  KEY `FK_prices_products` (`PRODUCT_ID`),
  CONSTRAINT `FK_prices_currencies` FOREIGN KEY (`CURRENCY_ID`) REFERENCES `currencies` (`CURRENCY_ID`),
  CONSTRAINT `FK_prices_products` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRODUCT_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.prices: ~6 rows (приблизительно)
DELETE FROM `prices`;
/*!40000 ALTER TABLE `prices` DISABLE KEYS */;
INSERT INTO `prices` (`PRICE_ID`, `CURRENCY_ID`, `PRODUCT_ID`, `VALUE`) VALUES
	(1, 1, 1, 300.00),
	(2, 1, 2, 350.00),
	(3, 1, 3, 400.00),
	(4, 1, 6, 250.00),
	(5, 1, 4, 450.00),
	(6, 1, 5, 800.00);
/*!40000 ALTER TABLE `prices` ENABLE KEYS */;


-- Дамп структуры для таблица test.products
DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `PRODUCT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_GROUP_ID` int(11) DEFAULT NULL,
  `PRODUCT_TYPE_ID1` int(11) DEFAULT NULL,
  `TITLE` varchar(80) DEFAULT NULL,
  `PRODUCT_TYPE_ID2` int(11) DEFAULT NULL,
  `PRODUCT_SECOND_GROUP_ID` int(11) DEFAULT NULL,
  `SKU` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`PRODUCT_ID`),
  KEY `FK_products_product_groups` (`PRODUCT_GROUP_ID`),
  CONSTRAINT `FK_products_product_groups` FOREIGN KEY (`PRODUCT_GROUP_ID`) REFERENCES `product_groups` (`PRODUCT_GROUP_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.products: ~33 rows (приблизительно)
DELETE FROM `products`;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`PRODUCT_ID`, `PRODUCT_GROUP_ID`, `PRODUCT_TYPE_ID1`, `TITLE`, `PRODUCT_TYPE_ID2`, `PRODUCT_SECOND_GROUP_ID`, `SKU`) VALUES
	(1, 2, 1, 'ОРБИТА.Регистратор-Д2-4.0', 1, 100, 'ОР-Д2-4.0'),
	(2, 2, 2, 'ОРБИТА.Регистратор-Д2-4.0-А', 2, 100, 'ОР-Д2-4.0-А'),
	(3, 1, 1, 'ОРБИТА.Регистратор-Д3-4.1-А', 3, 101, 'ОР-Д3-4.1-А'),
	(4, 1, 2, 'ОРБИТА.Регистратор-Д3-4.2', 4, 101, 'ОР-Д3-4.2'),
	(5, 1, 1, 'ОРБИТА.Регистратор-Д4-8.4', 5, 101, 'ОР-Д4-8.4'),
	(6, 1, 1, 'ОРБИТА.Регистратор-Д3-4.1-К', 6, 100, 'ОР-Д3-4.1-К'),
	(7, 102, NULL, 'ОРБИТА.С-8-80', 1, NULL, 'О.С-8-80-R'),
	(8, 102, NULL, 'ОРБИТА.С-8-96', 1, NULL, 'О.С-8-96-RG'),
	(9, 102, NULL, 'ОРБИТА.С-16-32', 1, NULL, 'О.С-16-32'),
	(10, 102, NULL, 'ОРБИТА.С-16-96', 1, NULL, 'О.С-16-96'),
	(11, 102, NULL, 'ОРБИТА.С-16-128', 1, NULL, 'О.С-16-128'),
	(12, 102, NULL, 'ОРБИТА.С-16-96/32', 1, NULL, 'О.С-16-96/32'),
	(13, 103, NULL, 'ОРБИТА.Навигатор-02 (в.3)', NULL, NULL, 'ОН-02-3.0'),
	(14, 102, NULL, 'ОРБИТА.C-16-96/16-32-L-YH-(S.10-P.TL-N.0) [Ивеко СВ]', NULL, NULL, 'О.С-16-96/32 [Ив-СВ]'),
	(15, 102, NULL, 'ОРБИТА.С-16-128-YH-(S.10-P.TR-N.1) [Ивеко СВ]', NULL, NULL, 'О.С-16-128 [Ив-СВ]'),
	(16, 102, NULL, 'ОРБИТА.C-16-32-YH-(S.10-P.TM-V.BM-N.2-T) [Ивеко СВ]', NULL, NULL, 'О.С-16-32 [Ив-СВ]'),
	(17, 102, NULL, 'ОРБИТА.C-8-96-RG-(S.8-P.TL-N.3) [Ивеко СВ]', NULL, NULL, 'О.С-16-96 [Ив-СВ]'),
	(18, 103, NULL, 'ОРБИТА.Навигатор-02', NULL, NULL, 'ОН-02'),
	(19, 103, NULL, 'ОРБИТА.Информатор (вер 3.0)', NULL, NULL, 'ОИ-3.0'),
	(20, 1, NULL, 'ОРБИТА.ИБПВ', NULL, NULL, 'ОР-ИБПВ'),
	(21, 1, NULL, 'ОРБИТА.Регистратор-Д3-4.1-А (c 3G модулем)', NULL, NULL, 'ОР-Д3-4.1-А (3G)'),
	(22, 102, NULL, 'ОРБИТА.С-8-96-RG-(S.8-P.TL-N.3) [Ивеко СВ]', NULL, NULL, 'О.С-8-96-RG [Ив-СВ]'),
	(23, 102, NULL, 'ОРБИТА.С-32-192 (спец нет) [МАЗ БВ ТК Лизинг]', NULL, NULL, 'О.С-32-192 [МАЗ-БВТКЛ]'),
	(25, 102, NULL, 'ОРБИТА.С-16-96 (спец нет) [МАЗ БВ ТК Лизинг]', NULL, NULL, 'О.С-16-96 [МАЗ-БВТКЛ]'),
	(26, 102, NULL, 'ОРБИТА.С-16-32 [МАЗ БВ ТК Лизинг]', NULL, NULL, 'О.С-16-32 [МАЗ-БВТКЛ]'),
	(27, 103, NULL, 'Автоинформатор 2.4', NULL, NULL, 'О.АИ-2.4'),
	(28, 102, NULL, 'ОРБИТА.С-8-80-R [МАЗ БВ ТК Лизинг]', NULL, NULL, 'О.С-8-80-R [МАЗ-БВТКЛ]'),
	(29, 102, NULL, 'ОРБИТА.C-16-32-YH-(S.10-P.TL-N.2) [Ивеко МВ] лоб', NULL, NULL, 'О.С-16-32-L [Ив-МВ]'),
	(30, 102, NULL, 'ОРБИТА.C-16-96-YH-(S.10-P.BM-N.1) [Ивеко МВ]', NULL, NULL, 'О.С-16-96 [Ив-МВ]'),
	(31, 102, NULL, 'ОРБИТА.C-16-32-YH-(S.10-P.BM-N.2) [Ивеко МВ] бок', NULL, NULL, 'О.С-16-32-S [Ив-МВ]'),
	(32, 102, NULL, 'ОРБИТА.C-16-32-YH-(S.10-P.BR-V.BM-N.2-T) [Ивеко МВ] зад', NULL, NULL, 'О.С-16-32-V [Ив-МВ]'),
	(33, 102, NULL, 'ОРБИТА.C-8-96-RG-(S.8-P.TL-N.3) [Ивеко МВ]', NULL, NULL, 'О.С-8-96-RG [Ив-МВ]'),
	(34, 102, NULL, NULL, NULL, NULL, 'О.С-УНИК-16-64');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;


-- Дамп структуры для таблица test.product_components
DROP TABLE IF EXISTS `product_components`;
CREATE TABLE IF NOT EXISTS `product_components` (
  `PRODUCT_COMPONENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` int(11) DEFAULT NULL,
  `COMPONENT_ID` int(11) DEFAULT NULL,
  `AMOUNT` double DEFAULT NULL,
  PRIMARY KEY (`PRODUCT_COMPONENT_ID`),
  KEY `FK_product_components_products` (`PRODUCT_ID`),
  KEY `FK_product_components_components` (`COMPONENT_ID`),
  CONSTRAINT `FK_product_components_components` FOREIGN KEY (`COMPONENT_ID`) REFERENCES `components` (`COMPONENT_ID`),
  CONSTRAINT `FK_product_components_products` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRODUCT_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.product_components: ~69 rows (приблизительно)
DELETE FROM `product_components`;
/*!40000 ALTER TABLE `product_components` DISABLE KEYS */;
INSERT INTO `product_components` (`PRODUCT_COMPONENT_ID`, `PRODUCT_ID`, `COMPONENT_ID`, `AMOUNT`) VALUES
	(1, 9, 1, 1),
	(2, 9, 6, 1),
	(3, 9, 5, 1),
	(4, 10, 1, 3),
	(5, 10, 6, 1),
	(6, 10, 5, 3),
	(7, 11, 1, 4),
	(8, 11, 6, 1),
	(9, 11, 5, 4),
	(10, 15, 1, 4),
	(11, 15, 6, 1),
	(12, 15, 5, 4),
	(13, 16, 1, 1),
	(14, 16, 6, 1),
	(15, 16, 5, 1),
	(16, 17, 1, 3),
	(17, 17, 6, 1),
	(18, 17, 5, 3),
	(19, 12, 1, 4),
	(20, 12, 6, 1),
	(21, 12, 5, 4),
	(22, 14, 1, 4),
	(23, 14, 6, 1),
	(24, 14, 5, 4),
	(25, 7, 2, 10),
	(26, 7, 7, 1),
	(27, 7, 4, 1),
	(28, 8, 3, 12),
	(29, 8, 8, 2),
	(30, 8, 4, 1),
	(31, 22, 3, 12),
	(32, 22, 8, 2),
	(33, 22, 4, 1),
	(34, 21, 9, 1),
	(35, 21, 15, 2),
	(36, 21, 14, 1),
	(37, 21, 16, 0.1),
	(38, 21, 11, 1),
	(39, 21, 10, 1),
	(43, 25, 1, 3),
	(44, 25, 6, 1),
	(45, 25, 5, 3),
	(46, 26, 1, 1),
	(47, 26, 6, 1),
	(48, 26, 5, 1),
	(49, 28, 2, 10),
	(50, 28, 7, 1),
	(51, 28, 4, 1),
	(52, 23, 1, 12),
	(53, 23, 6, 1),
	(54, 23, 5, 12),
	(55, 31, 1, 1),
	(56, 31, 6, 1),
	(57, 31, 5, 1),
	(58, 32, 1, 1),
	(59, 32, 6, 1),
	(60, 32, 5, 1),
	(61, 30, 1, 3),
	(62, 30, 6, 1),
	(63, 30, 5, 3),
	(64, 29, 1, 1),
	(65, 29, 6, 1),
	(66, 29, 5, 1),
	(67, 33, 3, 12),
	(68, 33, 8, 2),
	(69, 33, 4, 1),
	(70, 3, 9, 1),
	(71, 34, 17, 1),
	(72, 34, 5, 10);
/*!40000 ALTER TABLE `product_components` ENABLE KEYS */;


-- Дамп структуры для таблица test.product_groups
DROP TABLE IF EXISTS `product_groups`;
CREATE TABLE IF NOT EXISTS `product_groups` (
  `PRODUCT_GROUP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PARENT_ID` int(11) DEFAULT NULL,
  `NAME` varchar(80) DEFAULT NULL,
  `SUB_NAME` varchar(80) DEFAULT NULL,
  `TITLE` varchar(180) DEFAULT NULL,
  PRIMARY KEY (`PRODUCT_GROUP_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.product_groups: ~8 rows (приблизительно)
DELETE FROM `product_groups`;
/*!40000 ALTER TABLE `product_groups` DISABLE KEYS */;
INSERT INTO `product_groups` (`PRODUCT_GROUP_ID`, `PARENT_ID`, `NAME`, `SUB_NAME`, `TITLE`) VALUES
	(1, 0, 'Видеорегистраторы', 'мобильные', 'Видеорегистраторы мобильные'),
	(2, 1, 'Без IP камер', 'подключение 720p камер', 'Видеорегистраторы без IP камер, 720p'),
	(3, 0, 'Видеокамеры', 'транспортные', 'Видеокамеры транспортные'),
	(4, 1, 'С IP камерами', 'подключение IP камер', 'Видеорегистраторы с IP камерами'),
	(100, 0, 'Эконом', NULL, 'Товары эконом'),
	(101, 0, 'Спеццена', NULL, 'Товары со спецценой'),
	(102, 0, 'Светодиодные табло', NULL, 'Светодиодные табло'),
	(103, 0, 'Навигация', NULL, 'Навигационная аппаратура');
/*!40000 ALTER TABLE `product_groups` ENABLE KEYS */;


-- Дамп структуры для таблица test.product_parameters
DROP TABLE IF EXISTS `product_parameters`;
CREATE TABLE IF NOT EXISTS `product_parameters` (
  `PRODUCT_PARAMETER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` int(11) NOT NULL DEFAULT '0',
  `PARAMETER_ID` int(11) NOT NULL DEFAULT '0',
  `VALUE_STRING` varchar(250) NOT NULL DEFAULT '0',
  PRIMARY KEY (`PRODUCT_PARAMETER_ID`),
  KEY `FK__products` (`PRODUCT_ID`),
  KEY `FK__parameters` (`PARAMETER_ID`),
  CONSTRAINT `FK__parameters` FOREIGN KEY (`PARAMETER_ID`) REFERENCES `parameters` (`PARAMETER_ID`),
  CONSTRAINT `FK__products` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRODUCT_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.product_parameters: ~6 rows (приблизительно)
DELETE FROM `product_parameters`;
/*!40000 ALTER TABLE `product_parameters` DISABLE KEYS */;
INSERT INTO `product_parameters` (`PRODUCT_PARAMETER_ID`, `PRODUCT_ID`, `PARAMETER_ID`, `VALUE_STRING`) VALUES
	(2, 1, 1, '4 кам для Д2-40'),
	(3, 1, 3, '12 воль для д2-40'),
	(4, 1, 5, '200 г д240'),
	(5, 3, 1, '4 ан кам Д341а'),
	(6, 3, 2, '1 ип кам д341а'),
	(7, 3, 5, '180 гр');
/*!40000 ALTER TABLE `product_parameters` ENABLE KEYS */;


-- Дамп структуры для таблица test.product_types
DROP TABLE IF EXISTS `product_types`;
CREATE TABLE IF NOT EXISTS `product_types` (
  `PRODUCT_TYPE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(50) NOT NULL,
  PRIMARY KEY (`PRODUCT_TYPE_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.product_types: ~3 rows (приблизительно)
DELETE FROM `product_types`;
/*!40000 ALTER TABLE `product_types` DISABLE KEYS */;
INSERT INTO `product_types` (`PRODUCT_TYPE_ID`, `NAME`) VALUES
	(1, 'Товар'),
	(2, 'Услуга'),
	(3, 'Часть товара');
/*!40000 ALTER TABLE `product_types` ENABLE KEYS */;


-- Дамп структуры для таблица test.projects
DROP TABLE IF EXISTS `projects`;
CREATE TABLE IF NOT EXISTS `projects` (
  `PROJECT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(150) DEFAULT NULL,
  `CUSTOMER_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`PROJECT_ID`),
  KEY `FK_projects_customers` (`CUSTOMER_ID`),
  CONSTRAINT `FK_projects_customers` FOREIGN KEY (`CUSTOMER_ID`) REFERENCES `customers` (`CUSTOMER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.projects: ~3 rows (приблизительно)
DELETE FROM `projects`;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` (`PROJECT_ID`, `NAME`, `CUSTOMER_ID`) VALUES
	(1, 'Ивеко СВ', 2),
	(2, 'МАЗ БВ ТК Лизинг', 2),
	(3, 'Ивеко МВ', 2);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;


-- Дамп структуры для таблица test.sys_clients
DROP TABLE IF EXISTS `sys_clients`;
CREATE TABLE IF NOT EXISTS `sys_clients` (
  `CLIENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CLIENT_KEY` varchar(50) DEFAULT NULL,
  `USER_ID` int(11) DEFAULT NULL,
  `REFRESH_TIME` timestamp NULL DEFAULT NULL,
  `OPEN_TIME` timestamp NULL DEFAULT NULL,
  `USER_ASK_REMEMBER` int(11) DEFAULT NULL,
  PRIMARY KEY (`CLIENT_ID`),
  UNIQUE KEY `CLIENT_KEY` (`CLIENT_KEY`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.sys_clients: ~17 rows (приблизительно)
DELETE FROM `sys_clients`;
/*!40000 ALTER TABLE `sys_clients` DISABLE KEYS */;
INSERT INTO `sys_clients` (`CLIENT_ID`, `CLIENT_KEY`, `USER_ID`, `REFRESH_TIME`, `OPEN_TIME`, `USER_ASK_REMEMBER`) VALUES
	(1, '5184f08c843a6931856b7601466d4f809d0bc0ca', 0, '2016-02-22 19:52:42', '2016-02-22 19:52:42', NULL),
	(2, '8714d4de9552a993d6e1013cdb5e5337e61e3261', 0, '2016-02-22 19:53:18', '2016-02-22 19:53:18', NULL),
	(3, '76c0fc2e50c604dd73dd3f3668d16cf86ec73dbd', 0, '2016-02-22 19:54:02', '2016-02-22 19:54:02', NULL),
	(4, '201bcd3bac63a2d81fdbbe13b6c58d3fd798a4cf', 0, '2016-02-22 19:54:19', '2016-02-22 19:54:19', NULL),
	(5, '5bf96847b8aa71f39e461a40fb7d57c94edbf3a6', 0, '2016-02-22 19:54:53', '2016-02-22 19:54:53', NULL),
	(6, '432aa35a8df611dfd163442dfca2b6d5426879f4', 0, '2016-02-22 19:55:14', '2016-02-22 19:55:14', NULL),
	(7, '853dafef5f6b5edd8f374d6aea23697db9ef44e0', 0, '2016-02-22 19:55:27', '2016-02-22 19:55:27', NULL),
	(8, '46f8e098a7bd71db3feb51a7dbb1311e96013ef6', 0, '2016-02-22 19:55:38', '2016-02-22 19:55:38', NULL),
	(9, '3ec6e827420d2c4cd5ca7eda3bfa3196d91a982c', 0, '2016-02-22 19:59:19', '2016-02-22 19:59:19', NULL),
	(10, 'c5dbe89d0eb6024e91371fff3a349d07089a4e10', 0, '2016-02-22 19:59:24', '2016-02-22 19:59:24', NULL),
	(11, '839f19012b39a4c0b6eef1e04ea321fcc05541c2', 0, '2016-02-22 19:59:28', '2016-02-22 19:59:28', NULL),
	(12, '043fe9ba8d0786307cd5e30ca74940c72730663c', 0, '2016-02-22 19:59:32', '2016-02-22 19:59:32', NULL),
	(13, '095ecf097e9d68852f3145eaab0a9c65f3f4a49f', 0, '2016-02-22 19:59:36', '2016-02-22 19:59:36', NULL),
	(14, '46ca238a4eaf5fd9f9be171d2a255f289b4b801e', 0, '2016-02-22 19:59:40', '2016-02-22 19:59:40', NULL),
	(15, 'c47fc939738344567218c4aafaae1a48edcfd090', 0, '2016-02-22 20:02:52', '2016-02-22 20:02:52', NULL),
	(16, 'd189998457c8e80bf0c78e12e7c0d0ed3dab298e', 6, '2016-03-12 17:52:01', '2016-02-22 20:04:37', 1),
	(17, '9cd192dbaf1761bb29e47a3b162874f5739cbc72', 0, '2016-03-26 19:09:28', '2016-03-26 19:09:28', NULL),
	(18, 'a2a8932a44885b2e32912a898386e561e1384f91', 0, '2017-04-13 13:01:55', '2017-04-13 13:01:55', NULL),
	(19, '020be164485d1c3ff3dc6486e31f8099d7a0f43e', 0, '2019-06-17 14:43:05', '2019-06-17 14:06:35', NULL),
	(20, 'd109b18214df78edb97973322af0fde975c11d75', 12, '2019-06-22 12:58:23', '2019-06-18 12:13:04', 1);
/*!40000 ALTER TABLE `sys_clients` ENABLE KEYS */;


-- Дамп структуры для таблица test.sys_form_nonces
DROP TABLE IF EXISTS `sys_form_nonces`;
CREATE TABLE IF NOT EXISTS `sys_form_nonces` (
  `SERVER_SECRET` varchar(50) NOT NULL,
  `OPEN_TIME` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `NONCE` varchar(100) NOT NULL,
  `SESSION_KEY` varchar(100) NOT NULL,
  PRIMARY KEY (`SERVER_SECRET`),
  UNIQUE KEY `NONCE` (`NONCE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.sys_form_nonces: ~1 rows (приблизительно)
DELETE FROM `sys_form_nonces`;
/*!40000 ALTER TABLE `sys_form_nonces` DISABLE KEYS */;
INSERT INTO `sys_form_nonces` (`SERVER_SECRET`, `OPEN_TIME`, `NONCE`, `SESSION_KEY`) VALUES
	('59d926e497c2fa1e4bd5a665af5f53e5', '2019-06-22 12:58:18', '88a7ad0a9cffb8206734c281cf6ed2f99834d404', '86e944caf9c333cd558988a4a2f980e778004f1f');
/*!40000 ALTER TABLE `sys_form_nonces` ENABLE KEYS */;


-- Дамп структуры для таблица test.sys_persons
DROP TABLE IF EXISTS `sys_persons`;
CREATE TABLE IF NOT EXISTS `sys_persons` (
  `PERSON_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) DEFAULT NULL,
  `FIRST_NAME` varchar(50) DEFAULT NULL,
  `LAST_NAME` varchar(50) DEFAULT NULL,
  `MIDDLE_NAME` varchar(50) DEFAULT NULL,
  `PHONE` varchar(50) DEFAULT NULL,
  `PHONE_2` varchar(50) DEFAULT NULL,
  `CONTACT_EMAIL` varchar(150) DEFAULT NULL,
  `ORG_NAME` varchar(150) DEFAULT NULL,
  `DATE_BEGIN` date DEFAULT NULL,
  `DATE_END` date DEFAULT NULL,
  PRIMARY KEY (`PERSON_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.sys_persons: ~8 rows (приблизительно)
DELETE FROM `sys_persons`;
/*!40000 ALTER TABLE `sys_persons` DISABLE KEYS */;
INSERT INTO `sys_persons` (`PERSON_ID`, `USER_ID`, `FIRST_NAME`, `LAST_NAME`, `MIDDLE_NAME`, `PHONE`, `PHONE_2`, `CONTACT_EMAIL`, `ORG_NAME`, `DATE_BEGIN`, `DATE_END`) VALUES
	(1, NULL, 'Андрей', 'Харьковский', NULL, '88002508778', NULL, NULL, NULL, '2016-02-08', NULL),
	(2, NULL, 'Кирилл', 'Бутаков', 'Александрович', NULL, '+7 964 528-39-63', NULL, NULL, NULL, NULL),
	(3, NULL, 'Геннадий', 'Гудумак', 'Климентьевич', NULL, '+7(926)023 30 87', 'g.gudumak@transtelematica.ru', NULL, NULL, NULL),
	(5, NULL, 'Василий', 'Логайчук', 'Иванович', ' +7 (495) 589-24-12 (доб. 2012)', '+7 963 697-54-35', 'V.Logaychuk@transtelematica.ru', NULL, NULL, NULL),
	(6, 7, 'toni', 'tonf', NULL, '2189-1231', NULL, 'jk@hjhj', 'tona', NULL, NULL),
	(7, 9, 'dhsdf', 'sdd', NULL, '34252', NULL, 'ssd@jkhjkhjkhjkhk', 'sdasd', NULL, NULL),
	(8, 10, 'tatana', 'tatna', NULL, '', NULL, '', 'tataorg', NULL, NULL),
	(9, 11, 'fgsdh', 'gdsfgsdfg', NULL, '234513454', NULL, '', 'sadf', NULL, NULL),
	(10, 12, 'ghdfgjdf', 'dfgjdf', NULL, '45635', NULL, 'dfs@dgsd', 'sdgsdffg', NULL, NULL);
/*!40000 ALTER TABLE `sys_persons` ENABLE KEYS */;


-- Дамп структуры для таблица test.sys_sessions
DROP TABLE IF EXISTS `sys_sessions`;
CREATE TABLE IF NOT EXISTS `sys_sessions` (
  `SESSION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SESSION_KEY` varchar(41) DEFAULT NULL,
  `CLIENT_ID` int(11) NOT NULL DEFAULT '0',
  `CLIENT_IP_ADDR` varchar(50) DEFAULT NULL,
  `SERIALIZED_DATA` varchar(2048) DEFAULT NULL,
  `OPEN_TIME` timestamp NULL DEFAULT NULL,
  `REFRESH_TIME` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`SESSION_ID`),
  KEY `SESSION_KEY` (`SESSION_KEY`)
) ENGINE=MEMORY AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 MAX_ROWS=1000;

-- Дамп данных таблицы test.sys_sessions: 1 rows
DELETE FROM `sys_sessions`;
/*!40000 ALTER TABLE `sys_sessions` DISABLE KEYS */;
INSERT INTO `sys_sessions` (`SESSION_ID`, `SESSION_KEY`, `CLIENT_ID`, `CLIENT_IP_ADDR`, `SERIALIZED_DATA`, `OPEN_TIME`, `REFRESH_TIME`) VALUES
	(1, '86e944caf9c333cd558988a4a2f980e778004f1f', 20, '127.0.0.1', NULL, '2019-06-22 12:58:16', '2019-06-22 12:58:23');
/*!40000 ALTER TABLE `sys_sessions` ENABLE KEYS */;


-- Дамп структуры для таблица test.sys_signups
DROP TABLE IF EXISTS `sys_signups`;
CREATE TABLE IF NOT EXISTS `sys_signups` (
  `SIGNUP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LOGIN` varchar(50) NOT NULL,
  `USER_EMAIL` varchar(70) NOT NULL,
  `PASS_HASH` varchar(150) NOT NULL,
  `CNONCENONCE` varchar(200) DEFAULT NULL,
  `IP_SOURCE` varchar(46) NOT NULL,
  `FIRST_NAME` varchar(50) NOT NULL,
  `LAST_NAME` varchar(50) NOT NULL,
  `MIDDLE_NAME` varchar(50) NOT NULL,
  `REQUEST_TIME` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `PHONE` varchar(50) NOT NULL,
  `PHONE_2` varchar(50) NOT NULL,
  `COMMENTS` text,
  `ORG_NAME` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`SIGNUP_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.sys_signups: ~7 rows (приблизительно)
DELETE FROM `sys_signups`;
/*!40000 ALTER TABLE `sys_signups` DISABLE KEYS */;
INSERT INTO `sys_signups` (`SIGNUP_ID`, `LOGIN`, `USER_EMAIL`, `PASS_HASH`, `CNONCENONCE`, `IP_SOURCE`, `FIRST_NAME`, `LAST_NAME`, `MIDDLE_NAME`, `REQUEST_TIME`, `PHONE`, `PHONE_2`, `COMMENTS`, `ORG_NAME`) VALUES
	(15, 'bbb', '', '529ecd2f2b46fd7e2559929ba8f72faa04835952', '6ca867d235443b780bf7bee2cce3904b2c948475', '', '', '', '', '2016-02-16 20:28:22', '', '', NULL, NULL),
	(16, 'asdasda', '', '28c595a471ed39f45db7e63e603bbb300247e608', 'c69eef8dc398c38c9af9e00e30d7f2cb94940ce5', '', '', '', '', '2016-02-16 20:33:34', '', '', NULL, NULL),
	(17, 'zoo', '', 'd04f51d1252efb9777a6f716c07d5afe129be5e4', '8371094f5bb62b637d90e5ba325e0a1a832388f6', '', '', '', '', '2016-02-17 19:32:00', '', '', NULL, NULL),
	(18, 'wqw', '', '251c327facea92023e586060937272e30cb0d16a', '4590fc5bc43910f78664c528a0c5dd3eec4265ce', '', '', '', '', '2016-02-17 20:07:06', '', '', NULL, NULL),
	(19, 'sdf', '', '753156b261f7aed35e48c28ff256aa27938b910d', '3c1ab55cd20e7848c3331451f2e8a9972d15083d', '', '', '', '', '2016-02-17 20:34:27', '', '', NULL, NULL),
	(20, 'test', '', '8e08f9eb9bd21cc3f5762e9ab42f71a5da2ca8ce', '3aa436b35af4f1c93fd2f811725e96cb600bbcdd', '', '', '', '', '2019-06-18 12:27:53', '', '', NULL, NULL),
	(21, 'mina', '', '03485db51fbcb48f1a2c67b5b724e5399b70abda', '340b7d827f416bf46976595ebf607cb190db55ba', '', '', '', '', '2019-06-18 12:34:29', '', '', NULL, NULL);
/*!40000 ALTER TABLE `sys_signups` ENABLE KEYS */;


-- Дамп структуры для таблица test.sys_users
DROP TABLE IF EXISTS `sys_users`;
CREATE TABLE IF NOT EXISTS `sys_users` (
  `USER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LOGIN` varchar(50) NOT NULL,
  `ACTIVE_PERSON_ID` int(11) DEFAULT NULL,
  `IS_DISABLED` int(11) DEFAULT NULL,
  `CNONCENONCE` varchar(50) DEFAULT NULL,
  `PASS_HASH` varchar(50) DEFAULT NULL,
  `USER_EMAIL` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`USER_ID`),
  KEY `FK_users_persons` (`ACTIVE_PERSON_ID`),
  CONSTRAINT `FK_users_persons` FOREIGN KEY (`ACTIVE_PERSON_ID`) REFERENCES `sys_persons` (`PERSON_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы test.sys_users: ~8 rows (приблизительно)
DELETE FROM `sys_users`;
/*!40000 ALTER TABLE `sys_users` DISABLE KEYS */;
INSERT INTO `sys_users` (`USER_ID`, `LOGIN`, `ACTIVE_PERSON_ID`, `IS_DISABLED`, `CNONCENONCE`, `PASS_HASH`, `USER_EMAIL`) VALUES
	(1, 'admin', 1, NULL, NULL, NULL, NULL),
	(2, 'harkov', 1, NULL, NULL, NULL, NULL),
	(5, 'bas', 3, NULL, 'f33db675c8a1254b2e7c8fe6733e2c9df460f94e', '868ae8e48658a7671ce382b03a5ff016a6913c67', NULL),
	(6, 'aaa', 2, NULL, 'dcf2422d9f51548158d8977e4a699f01dde0e1b5', 'a283926e0f59c6ba5de1306f08c9e9b46426260f', NULL),
	(7, 'ton', NULL, NULL, 'fee6b5bbb586c181704336ec0213c7d37889bbdd', 'bf0dfa1e3c5dc70fd54f9e5388b08b845587ac30', 'jk@hjhj'),
	(8, 'testo1', NULL, NULL, '96344bd6ba731e60f7fd8f140571419c150a651a', 'f82c17c87081e30110802e1383f5a9a3db8e1a38', 'jghjh@hjhjhjkkhkjhk'),
	(9, 'jhgjh', NULL, NULL, '13a26954fe6e67ec40a95db23e0689948ba2901b', '6505e62233175c059d6f7df7ccb78b312d63e99d', 'ssd@jkhjkhjkhjkhk'),
	(10, 'tata', NULL, NULL, 'b3d89bc9314d565f42e23b87c1ad93a87918f386', 'daa20f26df196609bbca0cc90bd38391edcc7b8f', ''),
	(11, 'tutuu', NULL, NULL, '54560ba8f33f8b542d411d2fb38b630aec3f33fb', 'a7e6e29e9afd3035ee9a4ee0d1445f2b8a07bb0f', ''),
	(12, 'tatataz', NULL, 1, 'b4c9b65eccb4b8b6a6c4bb196abed2d914f04e12', '0c17e09e345017f9dac2372c63cc8208ea819498', 'dfs@dgsd');
/*!40000 ALTER TABLE `sys_users` ENABLE KEYS */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
