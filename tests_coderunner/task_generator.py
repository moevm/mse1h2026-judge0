import os

def generate_questions_xml(tasks, filename="coderunner_tasks.xml"):
    xml_parts = ['<?xml version="1.0" encoding="UTF-8"?>', '<quiz>']

    for task in tasks:
        question = f"""
  <question type="coderunner">
    <name>
      <text>{task['name']}</text>
    </name>
    <questiontext format="html">
      <text><![CDATA[<p>{task['description']}</p>]]></text>
    </questiontext>
    <coderunnertype>{task.get('language', 'python3')}</coderunnertype>
    <prototypetype>0</prototypetype>
    <allornothing>1</allornothing>
    <penalty>0.33</penalty>
    <answer><![CDATA[{task['answer']}]]></answer>
    <testcases>"""
        
        for test in task['tests']:
            question += f"""
      <testcase display="SHOW">
        <testcode>
          <text><![CDATA[{test['code']}]]></text>
        </testcode>
        <expected>
          <text><![CDATA[{test['expected']}]]></text>
        </expected>
      </testcase>"""
            
        question += """
    </testcases>
  </question>"""
        xml_parts.append(question)

    xml_parts.append('</quiz>')
    
    with open(filename, "w", encoding="utf-8") as f:
        f.write("\n".join(xml_parts))


TASKS = [
    {
        "name": "Python: Сложение",
        "language": "python3",
        "description": "Напишите функцию <b>sum_numbers(a, b)</b>",
        "answer": "def sum_numbers(a, b):\n    return a + b",
        "tests": [
            {"code": "print(sum_numbers(2, 2))", "expected": "4"},
            {"code": "print(sum_numbers(10, 5))", "expected": "15"},
        ]
    },
    {
        "name": "Python: Квадрат числа",
        "language": "python3",
        "description": "Напишите функцию <b>square(n)</b>",
        "answer": "def square(n):\n    return n * n",
        "tests": [
            {"code": "print(square(6))", "expected": "36"},
        ]
    }
]


generate_questions_xml(TASKS)
