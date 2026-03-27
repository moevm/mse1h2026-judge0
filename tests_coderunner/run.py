import subprocess
import sys
import os

def run_step(command, msg):
    print(f"\n>>> {msg}")
    res = subprocess.run([sys.executable] + command.split())
    if res.returncode != 0:
        print(f"Error on step: {msg}")
        sys.exit(1)

if __name__ == "__main__":
    run_step("task_generator.py", "XML generation")
    #run_step("", "Testing")
