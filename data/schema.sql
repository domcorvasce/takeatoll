CREATE TABLE customers (
  id serial,
  first_name varchar(100) not null,
  last_name varchar(100) not null,
  email varchar(255) not null,
  password text not null,
  created_at timestamp not null default NOW(),
  updated_at timestamp null,
  deleted_at timestamp null,
  unique(email),
  primary key(id)
);

INSERT INTO
  customers (first_name, last_name, email, password)
VALUES
  (
    'Michael',
    'Scott',
    'michael.scott@dundermifflin.com',
    '$2a$12$uhgItK6c7VQ7Gkd3EtpdjupOTHseNNGRZbTpmc8QdLi/PJAIygh3u'
  ),
  (
    'Dwight',
    'Schrute',
    'dwight.shrute@dundermifflin.com',
    '$2a$12$9uWtZRWfK5frSQnEb4aH9u3kTTVSKCG9fW8k3Uw8HL3vB3itLn.ki'
  ),
  (
    'Pamela Morgan',
    'Beesly',
    'pam.beesly@dundermifflin.com',
    '$2a$12$GQwS.T6XimEVTSVLhW9CheGDi.03IblfAMJaDswNZD67HVKcQsQ/m'
  ),
  (
    'Jim',
    'Halpert',
    'jim.halpert@athlead.com',
    '$2a$12$GQwS.T6XimEVTSVLhW9CheGDi.03IblfAMJaDswNZD67HVKcQsQ/m'
  ),
  (
    'Janet',
    'Levinson',
    'jan.levinson@dundermifflin.com',
    '$2a$12$8MTbCXT8jCzmntxSXqalzuBakQAnWrZFVw6xbvlVnGu9PsJPLaXDq'
  );

CREATE TABLE transponders (
  serial_number integer CHECK (serial_number > 0),
  customer_id integer not null,
  created_at timestamp not null default NOW(),
  deleted_at timestamp null,
  primary key(serial_number),
  foreign key(customer_id) references customers(id)
);

INSERT INTO
  transponders (serial_number, customer_id)
VALUES
  (28032022, 1),
  (31032022, 1),
  (16052013, 2),
  (24032005, 3),
  (80102008, 4),
  (70022013, 5);

CREATE TABLE stations (
  id serial,
  name varchar(512) not null,
  lat float not null,
  lng float not null,
  created_at timestamp not null default NOW(),
  updated_at timestamp null,
  deleted_at timestamp null,
  primary key(id),
  unique(lat, lng)
);

INSERT INTO
  stations (name, lat, lng)
VALUES
  ('Bologna Borgo Panigale', 44.5242602, 11.2433128),
  ('Milano Ovest', 44.5152626, 10.1057722),
  ('Rimini Sud', 44.035903, 12.5639777),
  ('Riccione', 43.9873964, 12.6388597),
  ('Pesaro - Urbino', 43.8974011, 12.8392112),
  ('Ancona Nord - Jesi', 43.5956983, 13.3549048),
  ('Napoli Est', 40.9159922, 14.468916),
  ('Caserta Nord', 41.0765751, 14.2999399),
  ('Canosa di Puglia', 41.2376737, 16.1047349),
  ('Foggia', 41.4980612, 15.5634077);

CREATE TABLE passthroughs (
  id serial,
  transponder_sn int not null,
  customer_id int not null,
  start_station_id int not null,
  end_station_id int null,
  cost float null,
  created_at timestamp not null default NOW(),
  updated_at timestamp null,
  primary key(id),
  foreign key(transponder_sn) references transponders(serial_number),
  foreign key(customer_id) references customers(id),
  foreign key(start_station_id) references stations(id),
  foreign key(end_station_id) references stations(id)
);

CREATE TABLE configuration_options (
  name varchar(64) not null,
  value varchar(64) not null,
  primary key(name)
);

INSERT INTO
  configuration_options (name, value)
VALUES
  ('pricingPlan', 'perDistance'),
  ('pricePerDistanceUnit', '0.35');
