#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
This script uses the curses library to create a simple form with a title field,
text field. Upon submission, it saves the entered data
to a MySQL database using environment variables for database credentials.

Usage:
    python curses_form.py

Dependencies:
    - curses (standard library)
    - mysql-connector-python (pip install mysql-connector-python)
    - os (standard library for environment variables)
    - datetime (standard library)
"""

__author__ = "Your Name"
__version__ = "1.0"
__date__ = "2025-01-05"
__license__ = "MIT"

import curses
import curses.textpad
import mysql.connector
from datetime import datetime
import os

save = False
text = ''

def main(stdscr):
    global save
    global text

    # Clear screen
    stdscr.clear()

    # Initialize color
    curses.start_color()
    curses.init_pair(1, curses.COLOR_BLUE, curses.COLOR_YELLOW)

    # Get screen dimensions
    height, width = stdscr.getmaxyx()

    # Create windows for form components
    title_win = curses.newwin(3, width - 4, 2, 2)
    text_win = curses.newwin(10, width - 4, 6, 2)

    # Set up title
    title_win.addstr(0, 0, "Enter a micro blog post", curses.A_BOLD)

    # Set up text field
    text_win.addstr(0, 0, "Text: ", curses.A_BOLD)
    text_edit = curses.textpad.Textbox(text_win.derwin(9, width - 10, 1, 0))

    # Refresh windows
    stdscr.refresh()
    title_win.refresh()
    text_win.refresh()

    # Wait for user input
    while True:
        # Edit text field
        stdscr.addstr(20, 2, "Editing Text: Press Ctrl+G when done.      ", curses.A_BOLD)
        stdscr.refresh()
        text = text_edit.edit().strip()

        if text:
            stdscr.addstr(20, 2, "Press Enter to save the entry or 'q' to quit. ", curses.A_BOLD)
            stdscr.refresh()
            key = stdscr.getch()
            if key == ord('q'):
                break
            elif key == ord('\n'):
                save = True
                break
        else:
            stdscr.addstr(20, 2, "Please fill in the text field. Press Enter to retry. ", curses.A_BOLD)
            stdscr.refresh()
            stdscr.getch()  # Wait for user to press Enter

def save_to_db(text):
    now = datetime.now()
    try:
        connection = mysql.connector.connect(
            host=os.getenv('MYSQL_HOST', 'localhost'),
            user=os.getenv('MYSQL_USER', 'default_user'),
            password=os.getenv('MYSQL_PASSWORD', 'default_pass'),
            database=os.getenv('MYSQL_DATABASE', 'default_db')
        )
    except mysql.connector.Error as error:
        print(f"Failed to connect to MySQL: {error}")
        return

    try:
        cursor = connection.cursor()
    except mysql.connector.Error as error:
        print(f"Failed to create cursor: {error}")
        if connection.is_connected():
            connection.close()
        return

    table_name = os.getenv('MYSQL_TABLE', 'default_table')
    insert_query = f"""INSERT INTO {table_name} (text, date_time) 
                       VALUES (%s, %s)"""

    try:
        cursor.execute(insert_query, (title, text, now))
    except mysql.connector.Error as error:
        print(f"Failed to execute query: {error}")
        if cursor:
            cursor.close()
        if connection.is_connected():
            connection.close()
        return

    try:
        connection.commit()
    except mysql.connector.Error as error:
        print(f"Failed to commit changes: {error}")
        if cursor:
            cursor.close()
        if connection.is_connected():
            connection.close()
        return

    print("Data inserted successfully")

    if cursor:
        try:
            cursor.close()
        except mysql.connector.Error as error:
            print(f"Failed to close cursor: {error}")
    
    if connection.is_connected():
        try:
            connection.close()
        except mysql.connector.Error as error:
            print(f"Failed to close database connection: {error}")

# Run the main function with curses wrapper
curses.wrapper(main)

if save:
    save_to_db(text)
