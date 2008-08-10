CREATE TABLE meals (
       meal_id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(255) NOT NULL
);

CREATE TABLE groups (
       group_id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
       street_name varchar(4000) NOT NULL,
       rehearsal_dessert_invite BOOLEAN NOT NULL DEFAULT 0,
       wants_share BOOLEAN NOT NULL DEFAULT 0,
       share_details TEXT NOT NULL DEFAULT "",
       INDEX (street_name)
);

CREATE TABLE people (
       person_id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
       group_id INTEGER NOT NULL,
       INDEX (group_id),
       name VARCHAR(255) NOT NULL,
       attending BOOLEAN NULL,
       attending_dessert BOOLEAN NULL,
       meal INTEGER NULL
);

CREATE TABLE sessions (
       session_id CHAR(32) NOT NULL PRIMARY KEY,
       session_data TEXT NOT NULL
);

CREATE TABLE changes (
       change_id integer not null auto_increment primary key,
       change_time CHAR(20) NOT NULL,
       INDEX(change_time),
       change_text TEXT NOT NULL
);