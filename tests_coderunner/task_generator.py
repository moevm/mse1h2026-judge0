import xml.etree.ElementTree as ET

def create_coderunner_xml(filename="coderunner_tasks.xml"):
    quiz = ET.Element("quiz")
    q = ET.SubElement(quiz, "question", type="coderunner")
    ET.SubElement(ET.SubElement(q, "name"), "text").text = "Проверка сложения"
    ET.SubElement(ET.SubElement(q, "questiontext", format="html"), "text").text = "<![CDATA[Напишите функцию sum_numbers(a, b)]]>"
    ET.SubElement(q, "coderunnertype").text = "python3"
    ET.SubElement(q, "answer").text = "def sum_numbers(a, b):\n    return a + b"
    
    tcs = ET.SubElement(q, "testcases")
    tc = ET.SubElement(tcs, "testcase")
    ET.SubElement(tc, "testcode").text = "print(sum_numbers(5, 5))"
    ET.SubElement(tc, "expected").text = "10"
    ET.SubElement(tc, "display").text = "SHOW"

    tree = ET.ElementTree(quiz)
    tree.write(filename, encoding="UTF-8", xml_declaration=True)


create_coderunner_xml()
