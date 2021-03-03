CREATE TABLE /*_*/search_parameters (
    page_id INT NOT NULL,
    parameter_key VARCHAR(255) NOT NULL,
    parameter_value VARCHAR(1024) NOT NULL,
    PRIMARY KEY (page_id, parameter_key)
)