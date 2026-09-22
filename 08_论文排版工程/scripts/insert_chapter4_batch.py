"""
批量插入第4章 4.1-4.4 精修内容到Word文档
使用 word-mcp-live 的 Python API
"""
import sys
import json
import subprocess
from pathlib import Path

DOC = r"C:\Users\30625\Desktop\广州体育学院-何天元-毕业论文.docx"

# MCP server 运行在 localhost:8765
MCP_URL = "http://localhost:8765"

def mcp_call(tool_name, params):
    """通过 stdio 调用 MCP server """
    payload = json.dumps({
        "jsonrpc": "2.0",
        "id": 1,
        "method": "tools/call",
        "params": {
            "name": tool_name,
            "arguments": params
        }
    })
    result = subprocess.run(
        ["python", "-c", f"""
import subprocess, json, sys
# Start MCP server and send request
proc = subprocess.Popen(
    ['uvx', '--from', 'word-mcp-live', 'word_mpc_server.exe'],
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    text=True
)
out, err = proc.communicate(input=json.dumps({json.dumps(tool_name)}, {json.dumps(params)}))
print(out)
print(err, file=sys.stderr)
        """],
        capture_output=True, text=True, cwd=Path(__file__).parent
    )
    print(result.stdout[:500])
    print(result.stderr[:200])
    return result.stdout

# 读取文档信息
print("读取当前文档...")
result = subprocess.run(
    ["python", "-c", """
import sys
sys.path.insert(0, '.')
from word_mcp_client import word_server, word_server_call
result = word_server_call('word_get_document_info', {{'file_path': r'C:\\\\Users\\\\30625\\\\Desktop\\\\广州体育学院-何天元-毕业论文.docx'}})
print(result)
    """],
    capture_output=True, text=True
)
print(result.stdout)
